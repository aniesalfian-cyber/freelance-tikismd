<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\SystemBackup;
use App\Services\BackupService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function __construct(protected BackupService $backups) {}

    public function index()
    {
        return view('admin.backups.index', [
            'backups' => SystemBackup::with('creator:id,name')->latest()->paginate(10),
            'phrase' => config('hsm.restore_confirmation_phrase'),
            'maxMb' => config('hsm.backup_max_upload_mb'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $backup = $this->backups->create('manual_superadmin', $request->user());

        return back()->with('success', "Backup berhasil dibuat: {$backup->filename} ({$backup->humanSize()}).");
    }

    public function download(SystemBackup $backup)
    {
        abort_unless($backup->status === 'success' && Storage::disk('local')->exists($backup->file_path), 404, 'Berkas backup tidak ditemukan.');
        ActivityLogger::log('backup.downloaded', "Mengunduh backup {$backup->filename}", $backup);

        return Storage::disk('local')->download($backup->file_path, $backup->filename);
    }

    public function destroy(SystemBackup $backup): RedirectResponse
    {
        ActivityLogger::log('backup.deleted', "Menghapus backup {$backup->filename}");
        $this->backups->delete($backup);

        return back()->with('success', 'Backup dihapus.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $phrase = config('hsm.restore_confirmation_phrase');
        $request->validate([
            'backup_file' => ['required', 'file', 'max:'.(config('hsm.backup_max_upload_mb') * 1024)],
            'confirmation' => ['required', 'in:'.$phrase],
        ], [
            'confirmation.in' => "Ketik tepat \"{$phrase}\" untuk mengonfirmasi.",
            'backup_file.required' => 'Pilih berkas backup (.sql / .sql.gz).',
        ]);

        $file = $request->file('backup_file');
        $name = $file->getClientOriginalName();
        if (! preg_match('/\.(sql|sql\.gz)$/i', $name)) {
            throw new BusinessRuleException('Format berkas harus .sql atau .sql.gz.');
        }

        $this->backups->restore($file->getRealPath(), $name, $request->user());

        // Sesi bisa tertimpa data restore; paksa login ulang yang bersih.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Database berhasil dipulihkan. Silakan login kembali.');
    }
}
