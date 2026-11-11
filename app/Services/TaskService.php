<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            $proofPath = $proof->store('proofs/'.$now->format('Y/m'), 'public');
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

    protected function assertOwner(TaskAssignment $task, User $user): void
    {
        if ($task->user_id !== $user->id) {
            throw new BusinessRuleException('Tugas ini bukan milik Anda.', [], 403);
        }
    }
}
