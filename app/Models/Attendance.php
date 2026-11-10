<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const STATUS_ON_TIME = 'hadir_tepat';
    public const STATUS_LATE = 'terlambat';
    public const STATUS_INVALID = 'invalid_radius';

    public const STATUS_LABELS = [
        self::STATUS_ON_TIME => 'Hadir Tepat Waktu',
        self::STATUS_LATE => 'Terlambat',
        self::STATUS_INVALID => 'Di Luar Radius',
    ];

    protected $fillable = [
        'user_id', 'outlet_id', 'date', 'check_in_at', 'check_in_latitude', 'check_in_longitude',
        'check_in_distance_meters', 'check_in_accuracy', 'late_minutes',
        'check_out_at', 'check_out_latitude', 'check_out_longitude', 'check_out_distance_meters',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_in_accuracy' => 'float',
            'late_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_ON_TIME => 'badge-success',
            self::STATUS_LATE => 'badge-warning',
            default => 'badge-danger',
        };
    }

    public function workMinutes(): ?int
    {
        return $this->check_out_at ? $this->check_in_at->diffInMinutes($this->check_out_at) : null;
    }
}
