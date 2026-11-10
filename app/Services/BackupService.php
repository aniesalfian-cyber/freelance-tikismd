<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\SystemBackup;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class BackupService
{
    public const DIRECTORY = 'backups';

    /** Buat backup `.sql.gz` seluruh database. Password dikirim via env MYSQL_PWD (tidak muncul di argumen proses). */
    public function create(string $type = 'manual_superadmin', ?User $by = null): SystemBackup
    {
        Storage::disk('local')->makeDirectory(self::DIRECTORY);

        $filename = 'hsm_'.now()->format('Ymd_His').'_'.substr(bin2hex(random_bytes(3)), 0, 6).'.sql.gz';
        $relative = self::DIRECTORY.'/'.$filename;
        $absolute = Storage::disk('local')->path($relative);

        $error = '';
        $gz = gzopen($absolute, 'wb9');
        if ($gz === false) {
            throw new BusinessRuleException('Tidak dapat menulis berkas backup. Periksa izin folder storage/app/backups.');
        }

        try {
            $process = new Process(
                array_merge([$this->binary('mysqldump')], $this->dumpOptions(), [$this->dbConfig('database')]),
                null,
                $this->processEnv(),
                null,
                900
            );
            $process->run(function ($kind, $buffer) use ($gz, &$error) {
                if ($kind === Process::OUT) {
                    gzwrite($gz, $buffer);
                } else {
                    $error .= $buffer;
                }
            });
            $ok = $process->isSuccessful();
        } catch (Throwable $e) {
            $ok = false;
            $error .= $e->getMessage();
        } finally {
            gzclose($gz);
        }

        $size = is_file($absolute) ? filesize($absolute) : 0;

        if (! $ok || $size < 200) {
            @unlink($absolute);
            $record = SystemBackup::create([
                'filename' => $filename, 'file_path' => $relative, 'file_size_bytes' => 0,
                'backup_type' => $type, 'status' => 'failed', 'error_message' => mb_substr(trim($error) ?: 'Dump kosong.', 0, 1000),
                'created_by' => $by?->id,
            ]);
            ActivityLogger::log('backup.failed', 'Backup database gagal: '.mb_substr($error, 0, 300), $record, $by?->id);
            throw new BusinessRuleException('Backup gagal: '.mb_substr(trim($error) ?: 'dump kosong', 0, 300));
        }

        $record = SystemBackup::create([
            'filename' => $filename, 'file_path' => $relative, 'file_size_bytes' => $size,
            'backup_type' => $type, 'status' => 'success', 'created_by' => $by?->id,
        ]);
        ActivityLogger::log('backup.created', "Backup database dibuat ({$type}): {$filename}", $record, $by?->id);

        $this->pruneAuto();

        return $record;
    }

    /**
     * Pulihkan database dari berkas .sql / .sql.gz yang diunggah.
     * Memvalidasi skema, membuat backup pengaman, lalu mengeksekusi restore.
     */
    public function restore(string $uploadedPath, string $originalName, User $by): void
    {
        $isGz = str_ends_with(strtolower($originalName), '.gz');
        $tmpSql = tempnam(sys_get_temp_dir(), 'hsm_restore_').'.sql';

        try {
            $this->materializeSql($uploadedPath, $isGz, $tmpSql);
            $this->assertValidDump($tmpSql);

            // Backup pengaman sebelum menimpa data.
            $safety = $this->create('manual_superadmin', $by);

            $in = fopen($tmpSql, 'rb');
            $process = new Process(
                array_merge([$this->binary('mysql')], $this->clientOptions(), [$this->dbConfig('database')]),
                null,
                $this->processEnv(),
                $in,
                1800
            );
            $process->run();
            fclose($in);

            if (! $process->isSuccessful()) {
                ActivityLogger::log('backup.restore_failed', 'Restore gagal: '.mb_substr($process->getErrorOutput(), 0, 300), null, $by->id);
                throw new BusinessRuleException('Restore gagal: '.mb_substr(trim($process->getErrorOutput()), 0, 300).' Backup pengaman tersimpan: '.$safety->filename);
            }

            $this->syncFromDisk();
            ActivityLogger::log('backup.restored', "Database dipulihkan dari berkas {$originalName} (backup pengaman: {$safety->filename})", null, $by->id);
        } finally {
            @unlink($tmpSql);
        }
    }

    /** Daftarkan kembali berkas backup di disk yang hilang dari tabel (mis. setelah restore). */
    public function syncFromDisk(): void
    {
        foreach (Storage::disk('local')->files(self::DIRECTORY) as $file) {
            if (! str_ends_with($file, '.sql.gz')) {
                continue;
            }
            if (SystemBackup::where('file_path', $file)->exists()) {
                continue;
            }
            SystemBackup::create([
                'filename' => basename($file),
                'file_path' => $file,
                'file_size_bytes' => Storage::disk('local')->size($file),
                'backup_type' => 'manual_superadmin',
                'status' => 'success',
            ]);
        }
    }

    public function delete(SystemBackup $backup): void
    {
        Storage::disk('local')->delete($backup->file_path);
        $backup->delete();
    }

    protected function pruneAuto(): void
    {
        $keep = (int) config('hsm.backup_keep_auto', 30);
        SystemBackup::where('backup_type', 'auto_scheduled')->where('status', 'success')
            ->orderByDesc('id')->skip($keep)->take(1000)->get()
            ->each(fn (SystemBackup $b) => $this->delete($b));
    }

    protected function materializeSql(string $source, bool $isGz, string $target): void
    {
        $out = fopen($target, 'wb');
        if ($isGz) {
            $in = gzopen($source, 'rb');
            if ($in === false) {
                throw new BusinessRuleException('Berkas .gz tidak dapat dibaca.');
            }
            while (! gzeof($in)) {
                $chunk = gzread($in, 1048576);
                if ($chunk === false) {
                    break;
                }
                fwrite($out, $chunk);
            }
            gzclose($in);
        } else {
            $in = fopen($source, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
    }

    /** Pastikan berkas berisi skema aplikasi HSM sebelum diizinkan menimpa database. */
    protected function assertValidDump(string $sqlPath): void
    {
        $required = ['`users`', '`outlets`', '`task_assignments`', '`attendances`'];
        $found = [];
        $h = fopen($sqlPath, 'rb');
        $carry = '';
        while (! feof($h)) {
            $chunk = $carry.fread($h, 1048576);
            foreach ($required as $table) {
                if (! isset($found[$table]) && stripos($chunk, 'CREATE TABLE '.$table) !== false) {
                    $found[$table] = true;
                }
                if (! isset($found[$table]) && stripos($chunk, 'CREATE TABLE IF NOT EXISTS '.$table) !== false) {
                    $found[$table] = true;
                }
            }
            $carry = substr($chunk, -256);
        }
        fclose($h);

        if (count($found) !== count($required)) {
            throw new BusinessRuleException('Berkas bukan backup database HSM yang valid (struktur tabel inti tidak ditemukan).');
        }
    }

    protected function dumpOptions(): array
    {
        return array_merge($this->clientOptions(), ['--single-transaction', '--routines', '--default-character-set=utf8mb4']);
    }

    protected function clientOptions(): array
    {
        $opts = ['--no-defaults', '--user='.$this->dbConfig('username')];
        $socket = $this->dbConfig('unix_socket');
        if ($socket) {
            $opts[] = '--socket='.$socket;
        } else {
            $opts[] = '--host='.$this->dbConfig('host');
            $opts[] = '--port='.$this->dbConfig('port');
        }

        return $opts;
    }

    protected function processEnv(): array
    {
        return ['MYSQL_PWD' => (string) $this->dbConfig('password')];
    }

    protected function dbConfig(string $key): mixed
    {
        return config('database.connections.'.config('database.default').'.'.$key);
    }

    protected function binary(string $which): string
    {
        $configured = config("hsm.{$which}_path");
        if ($configured && is_executable($configured)) {
            return $configured;
        }

        $finder = new ExecutableFinder();
        $candidates = $which === 'mysqldump' ? ['mysqldump', 'mariadb-dump'] : ['mysql', 'mariadb'];
        foreach ($candidates as $name) {
            if ($path = $finder->find($name)) {
                return $path;
            }
        }

        throw new BusinessRuleException("Utilitas {$which} tidak ditemukan di server. Set HSM_".strtoupper($which).'_PATH pada .env.');
    }
}
