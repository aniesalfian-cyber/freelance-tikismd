<?php

namespace App\Http\Controllers;

use App\Services\HsmAttendanceBridgeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HsmAttendanceMediaController extends Controller
{
    /**
     * Stream an attendance selfie photo stored by tikismd.id on the shared server.
     */
    public function selfie(Request $request, int $attendanceId, string $type, HsmAttendanceBridgeService $bridge): BinaryFileResponse
    {
        $photo = $bridge->findAuthorizedPhoto($attendanceId, $type, $request->user());
        if (! $photo) {
            abort(404, 'Foto selfie presensi tidak ditemukan.');
        }

        $relativePath = ltrim(str_replace('\\', '/', $photo['file_path']), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            abort(404);
        }

        $baseRoot = (string) config('services.hsm_attendance.storage_path', '');
        $realBase = $baseRoot !== '' ? realpath($baseRoot) : false;
        if ($realBase === false) {
            abort(404, 'Direktori penyimpanan foto presensi belum tersedia.');
        }

        $candidate = $realBase.DIRECTORY_SEPARATOR.$relativePath;
        $realFile = realpath($candidate);

        if ($realFile === false || ! str_starts_with($realFile, $realBase.DIRECTORY_SEPARATOR) || ! is_file($realFile)) {
            abort(404, 'File foto presensi tidak ditemukan.');
        }

        return response()->file($realFile, [
            'Content-Type' => $photo['mime_type'],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
