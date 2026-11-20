<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskService
{
    public function __construct(
        protected GeofencingService $geo,
        protected AttendanceService $attendance,
        protected PerformanceScoringService $scoring,
    ) {}

    /** pending → in_progress */
    public function start(TaskAssignment $task, User $user, float $lat, float $lng, ?float $accuracy): TaskAssignment
    {
        $this->assertOwner($task, $user);

        if ($task->status !== TaskAssignment::STATUS_PENDING) {
            throw new BusinessRuleException('Tugas ini tidak dalam status Belum Diproses.');
        }
        if (! $task->assigned_date->isToday()) {
            throw new BusinessRuleException('Tugas hanya dapat dimulai pada tanggal penugasan ('.$task->assigned_date->format('d/m/Y').').');
        }
        if (! $this->attendance->today($user)) {
            throw new BusinessRuleException('Presensi masuk diperlukan sebelum memulai tugas.');
        }

        [$ok, $message] = $this->geo->validateHardwareAccuracy($accuracy);
        if (! $ok) {
            throw new BusinessRuleException($message);
        }

        $outlet = $task->outlet;
        $distance = (int) round($this->geo->calculateDistance($lat, $lng, $outlet->latitude, $outlet->longitude));

        $task->update([
            'status' => TaskAssignment::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'start_latitude' => $lat,
            'start_longitude' => $lng,
            'start_distance_meters' => $distance,
        ]);

        $user->recordLiveLocation($lat, $lng, $accuracy);

        ActivityLogger::log('task.start', "Mulai tugas {$task->task_number} ({$task->title}), jarak {$distance} m dari gerai", $task, $user->id);

        return $task->fresh();
    }

    /** in_progress → completed */
    public function complete(TaskAssignment $task, User $user, float $lat, float $lng, ?float $accuracy, string $notes, ?UploadedFile $proof = null): TaskAssignment
    {
        $this->assertOwner($task, $user);

        if ($task->status !== TaskAssignment::STATUS_IN_PROGRESS) {
            throw new BusinessRuleException('Hanya tugas berstatus Sedang Diproses yang dapat diselesaikan.');
        }

        $notes = trim(strip_tags($notes));
        if (mb_strlen($notes) < 10) {
            throw new BusinessRuleException('Catatan pengerjaan wajib diisi minimal 10 karakter.');
        }

        [$ok, $message] = $this->geo->validateHardwareAccuracy($accuracy);
        if (! $ok) {
            throw new BusinessRuleException($message);
        }

        $outlet = $task->outlet;
        $distance = (int) round($this->geo->calculateDistance($lat, $lng, $outlet->latitude, $outlet->longitude));
        $now = now();
        $actual = max(1, (int) ceil($task->started_at->diffInSeconds($now) / 60));

        $startValid = $task->start_distance_meters !== null && $task->start_distance_meters <= $outlet->radius_meters;
        $endValid = $distance <= $outlet->radius_meters;

        $proofPath = null;
        if ($proof) {
            $proofPath = $this->storeCompressedProof($proof, $now);
        }

        DB::transaction(function () use ($task, $now, $lat, $lng, $distance, $actual, $notes, $proofPath, $startValid, $endValid) {
            $task->update([
                'status' => TaskAssignment::STATUS_COMPLETED,
                'completed_at' => $now,
                'end_latitude' => $lat,
                'end_longitude' => $lng,
                'end_distance_meters' => $distance,
                'actual_duration_minutes' => $actual,
                'notes' => $notes,
                'proof_image' => $proofPath ?? $task->proof_image,
                'is_location_valid' => $startValid && $endValid,
            ]);
        });

        $user->recordLiveLocation($lat, $lng, $accuracy);

        ActivityLogger::log('task.complete', "Selesai tugas {$task->task_number} durasi {$actual} menit (SLA {$task->estimated_duration_minutes})", $task, $user->id);

        // Skor KPI hari tersebut diperbarui secara instan.
        $this->scoring->calculateForUserDate($task->user, $task->assigned_date);

        return $task->fresh();
    }

    public function cancel(TaskAssignment $task, User $actor, string $reason): TaskAssignment
    {
        if ($task->isFinal()) {
            throw new BusinessRuleException('Tugas yang sudah selesai atau dibatalkan tidak dapat dibatalkan.');
        }

        $task->update(['status' => TaskAssignment::STATUS_CANCELLED, 'cancel_reason' => $reason]);
        ActivityLogger::log('task.cancel', "Batalkan tugas {$task->task_number}: {$reason}", $task, $actor->id);
        $this->scoring->calculateForUserDate($task->user, $task->assigned_date);

        return $task->fresh();
    }

    public function deleteProof(TaskAssignment $task): void
    {
        if ($task->proof_image) {
            Storage::disk('public')->delete($task->proof_image);
        }
    }

    /**
     * Simpan dan lakukan kompresi/resize sekunder di server (GD) agar ukuran file
     * konsisten ringan (< 350 KB) dan dimensi maksimal 1280px.
     */
    protected function storeCompressedProof(UploadedFile $file, Carbon $now): string
    {
        $dir = 'proofs/'.$now->format('Y/m');
        $fileName = Str::random(40).'.jpg';
        $relativeStoragePath = $dir.'/'.$fileName;

        // Jika ekstensi GD aktif, lakukan auto-resize max 1280px dan kompresi JPEG 75%
        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            try {
                $rawContent = file_get_contents($file->getRealPath());
                $srcImage = @imagecreatefromstring($rawContent);

                if ($srcImage !== false) {
                    // Normalisasi orientasi EXIF (kamera smartphone Samsung/iPhone)
                    if (function_exists('exif_read_data')) {
                        $exif = @exif_read_data($file->getRealPath());
                        if (! empty($exif['Orientation'])) {
                            switch ($exif['Orientation']) {
                                case 3:
                                    $srcImage = imagerotate($srcImage, 180, 0);
                                    break;
                                case 6:
                                    $srcImage = imagerotate($srcImage, -90, 0);
                                    break;
                                case 8:
                                    $srcImage = imagerotate($srcImage, 90, 0);
                                    break;
                            }
                        }
                    }

                    $origWidth = imagesx($srcImage);
                    $origHeight = imagesy($srcImage);
                    $maxDimension = 1280;

                    if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
                        if ($origWidth > $origHeight) {
                            $targetWidth = $maxDimension;
                            $targetHeight = (int) round(($origHeight * $maxDimension) / $origWidth);
                        } else {
                            $targetHeight = $maxDimension;
                            $targetWidth = (int) round(($origWidth * $maxDimension) / $origHeight);
                        }

                        $destImage = imagecreatetruecolor($targetWidth, $targetHeight);
                        $white = imagecolorallocate($destImage, 255, 255, 255);
                        imagefill($destImage, 0, 0, $white);
                        imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);
                        imagedestroy($srcImage);
                        $srcImage = $destImage;
                    }

                    // Tulis hasil kompresi langsung ke storage disk
                    ob_start();
                    imagejpeg($srcImage, null, 75);
                    $compressedData = ob_get_clean();
                    imagedestroy($srcImage);

                    if (! empty($compressedData)) {
                        Storage::disk('public')->put($relativeStoragePath, $compressedData);

                        return $relativeStoragePath;
                    }
                }
            } catch (\Throwable $e) {
                // Fallback ke penyimpanan default jika terjadi kegagalan pemrosesan grafis
            }
        }

        return $file->store($dir, 'public');
    }

    protected function assertOwner(TaskAssignment $task, User $user): void
    {
        if ($task->user_id !== $user->id) {
            throw new BusinessRuleException('Tugas ini bukan milik Anda.', [], 403);
        }
    }
}
