<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'url',
        'type',
        'task_id',
        'read_at',
        'pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Buat notifikasi in-app dan antrean push notification saat tugas baru diberikan ke kurir.
     */
    public static function notifyTaskAssigned(TaskAssignment $task): self
    {
        $task->loadMissing('outlet:id,name');
        $outletName = $task->outlet?->name ?? 'Lokasi Operasional';
        $timeStr = $task->startTimeShort();

        return self::create([
            'user_id' => $task->user_id,
            'title' => '📦 Tugas Baru: '.$task->title,
            'body' => "Lokasi: {$outletName} • Jadwal: {$timeStr} WIB (SLA {$task->estimated_duration_minutes} menit) • No: {$task->task_number}",
            'url' => route('pwa.tasks.show', $task),
            'type' => 'task_assigned',
            'task_id' => $task->id,
        ]);
    }
}
