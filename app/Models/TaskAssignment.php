<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAssignment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Belum Diproses',
        self::STATUS_IN_PROGRESS => 'Sedang Diproses',
        self::STATUS_COMPLETED => 'Selesai Diproses',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    public const TYPE_FIXED = 'pasti_harian';
    public const TYPE_ADHOC = 'tidak_pasti';

    public const TYPE_LABELS = [
        self::TYPE_FIXED => 'Tugas Pasti Harian',
        self::TYPE_ADHOC => 'Tugas Tidak Pasti',
    ];

    protected $fillable = [
        'task_number', 'user_id', 'outlet_id', 'task_template_id', 'title', 'description',
        'task_type', 'assigned_date', 'scheduled_start_time', 'estimated_duration_minutes',
        'status', 'started_at', 'start_latitude', 'start_longitude', 'start_distance_meters',
        'completed_at', 'end_latitude', 'end_longitude', 'end_distance_meters',
        'actual_duration_minutes', 'notes', 'proof_image', 'is_location_valid',
        'cancel_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_location_valid' => 'boolean',
            'estimated_duration_minutes' => 'integer',
            'actual_duration_minutes' => 'integer',
            'start_latitude' => 'float',
            'start_longitude' => 'float',
            'end_latitude' => 'float',
            'end_longitude' => 'float',
        ];
    }

    // ---- Relations ----

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---- Scopes ----

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('assigned_date', Carbon::parse($date)->toDateString());
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    // ---- Accessors / Helpers ----

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->task_type] ?? $this->task_type;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-muted',
            self::STATUS_IN_PROGRESS => 'badge-orange',
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_CANCELLED => 'badge-danger',
            default => 'badge-muted',
        };
    }

    public function startTimeShort(): string
    {
        return substr((string) $this->scheduled_start_time, 0, 5);
    }

    public function scheduledAt(): Carbon
    {
        return $this->assigned_date->copy()->setTimeFromTimeString((string) $this->scheduled_start_time);
    }

    /** Batas selesai sesuai SLA, dihitung dari waktu mulai aktual. */
    public function slaDeadline(): ?Carbon
    {
        return $this->started_at?->copy()->addMinutes($this->estimated_duration_minutes);
    }

    /** Pending dan sudah melewati jam target mulai. */
    public function isLateToStart(): bool
    {
        return $this->status === self::STATUS_PENDING && now()->greaterThan($this->scheduledAt());
    }

    /** Selisih menit durasi aktual terhadap estimasi SLA (positif = lebih lama). */
    public function deviationMinutes(): ?int
    {
        if ($this->actual_duration_minutes === null) {
            return null;
        }

        return $this->actual_duration_minutes - $this->estimated_duration_minutes;
    }

    public function proofUrl(): ?string
    {
        return $this->proof_image ? Storage::disk('public')->url($this->proof_image) : null;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    public static function generateNumber(?Carbon $date = null): string
    {
        $date ??= now();
        $prefix = 'TSK-'.$date->format('Ymd').'-';
        $last = static::query()->where('task_number', 'like', $prefix.'%')->orderByDesc('task_number')->value('task_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
