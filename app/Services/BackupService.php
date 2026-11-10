<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\SystemBackup;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use Throwable;

class BackupService
{
    public const DIRECTORY = 'backups';

    /**
     * Buat backup `.sql.gz` seluruh tabel database menggunakan koneksi PDO aktif.
     * Bekerja 100% di Dedicated/VPS maupun Shared Hosting (Hostinger) yang menonaktifkan proc_open/exec.
     */
    public function create(string $type = 'manual_superadmin', ?User $by = null): SystemBackup
    {
        Storage::disk('local')->makeDirectory(self::DIRECTORY);

        $filename = 'hsm_'.now()->format('Ymd_His').'_'.substr(bin2hex(random_bytes(3)), 0, 6).'.sql.gz';
        $relative = self::DIRECTORY.'/'.$filename;
        $absolute = Storage::disk('local')->path($relative);

        $error = '';
        $gz = @gzopen($absolute, 'wb9');
        if ($gz === false) {
            throw new BusinessRuleException('Tidak dapat menulis berkas backup. Periksa izin folder storage/app/backups.');
        }

        try {
            $this->dumpViaPdo($gz);
            $ok = true;
        } catch (Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        } finally {
            gzclose($gz);
        }

        $size = is_file($absolute) ? filesize($absolute) : 0;

        if (! $ok || $size < 100) {
            @unlink($absolute);
            $record = SystemBackup::create([
                'filename' => $filename,
                'file_path' => $relative,
                'file_size_bytes' => 0,
                'backup_type' => $type,
                'status' => 'failed',
                'error_message' => mb_substr(trim($error) ?: 'Dump kosong.', 0, 1000),
                'created_by' => $by?->id,
            ]);
            ActivityLogger::log('backup.failed', 'Backup database gagal: '.mb_substr($error, 0, 300), $record, $by?->id);
            throw new BusinessRuleException('Backup gagal: '.mb_substr(trim($error) ?: 'dump kosong', 0, 300));
        }

        $record = SystemBackup::create([
            'filename' => $filename,
            'file_path' => $relative,
            'file_size_bytes' => $size,
            'backup_type' => $type,
            'status' => 'success',
            'created_by' => $by?->id,
        ]);
        ActivityLogger::log('backup.created', "Backup database dibuat ({$type}): {$filename}", $record, $by?->id);

        $this->pruneAuto();

        return $record;
    }

    /**
     * Ekspor seluruh skema & data tabel MySQL/MariaDB via PDO ke resource gzip.
     */
    protected function dumpViaPdo($gz): void
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();

        gzwrite($gz, "-- Monitoring Freelancer HSM Database Backup\n");
        gzwrite($gz, '-- Database: `'.$dbName.'` | Generated: '.now()->toDateTimeString()."\n");
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);

        foreach ($tables as $tRow) {
            $table = $tRow[0];
            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            $createSql = $createRow[1] ?? '';

            gzwrite($gz, "-- --------------------------------------------------------\n");
            gzwrite($gz, "-- Table structure for `{$table}`\n");
            gzwrite($gz, "-- --------------------------------------------------------\n");
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n");
            gzwrite($gz, $createSql.";\n\n");

            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $batch = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $vals = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $vals[] = 'NULL';
                    } elseif (is_int($val) || is_float($val)) {
                        $vals[] = (string) $val;
                    } else {
                        $vals[] = $pdo->quote((string) $val);
                    }
                }
                $batch[] = '('.implode(',', $vals).')';

                if (count($batch) >= 150) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                gzwrite($gz, "INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * Pulihkan database dari berkas .sql / .sql.gz yang diunggah menggunakan PDO.
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

            $this->restoreViaPdo($tmpSql);

            $this->syncFromDisk();
            ActivityLogger::log('backup.restored', "Database dipulihkan dari berkas {$originalName} (backup pengaman: {$safety->filename})", null, $by->id);
        } catch (BusinessRuleException $e) {
            throw $e;
        } catch (Throwable $e) {
            ActivityLogger::log('backup.restore_failed', 'Restore gagal: '.mb_substr($e->getMessage(), 0, 300), null, $by->id);
            throw new BusinessRuleException('Restore gagal: '.mb_substr($e->getMessage(), 0, 300));
        } finally {
            @unlink($tmpSql);
        }
    }

    /**
     * Jalankan statement SQL dari file dump secara berurutan via PDO.
     */
    protected function restoreViaPdo(string $sqlPath): void
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');

        $handle = fopen($sqlPath, 'rb');
        $statement = '';
        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $statement .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                $pdo->exec($statement);
                $statement = '';
            }
        }
        fclose($handle);

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
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
}
