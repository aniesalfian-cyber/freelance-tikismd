<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskTemplate extends Model
{
    public const DAY_LABELS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    protected $fillable = [
        'title', 'description', 'task_type', 'target_division', 'scheduled_start_time',
        'estimated_duration_minutes', 'default_outlet_id', 'default_user_id',
        'active_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'active_days' => 'array',
            'is_active' => 'boolean',
            'estimated_duration_minutes' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'default_outlet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function runsOnIsoDay(int $isoDay): bool
    {
        $days = $this->active_days;

        return empty($days) || in_array($isoDay, array_map('intval', $days), true);
    }

    public function targetDivisionInfo(): ?array
    {
        return $this->target_division ? (User::DIVISIONS[$this->target_division] ?? null) : null;
    }

    public function startTimeShort(): string
    {
        return substr((string) $this->scheduled_start_time, 0, 5);
    }

    public function activeDaysLabel(): string
    {
        if (empty($this->active_days)) {
            return 'Setiap Hari';
        }
        $sorted = collect($this->active_days)->map('intval')->sort()->values();

        return $sorted->map(fn ($d) => self::DAY_LABELS[$d] ?? (string) $d)->implode(', ');
    }
}
