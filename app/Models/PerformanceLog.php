<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceLog extends Model
{
    public const GRADE_LABELS = [
        'A' => 'Sangat Unggul',
        'B' => 'Memuaskan',
        'C' => 'Cukup',
        'D' => 'Perlu Evaluasi',
    ];

    protected $fillable = [
        'user_id', 'evaluation_date', 'total_tasks', 'completed_tasks', 'on_time_tasks',
        'valid_location_tasks', 'total_work_minutes', 'completion_score', 'time_score',
        'location_score', 'attendance_score', 'final_kpi_score', 'grade', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',
            'completion_score' => 'float',
            'time_score' => 'float',
            'location_score' => 'float',
            'attendance_score' => 'float',
            'final_kpi_score' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gradeLabel(): string
    {
        return self::GRADE_LABELS[$this->grade] ?? $this->grade;
    }

    public static function badgeFor(string $grade): string
    {
        return match ($grade) {
            'A' => 'badge-success',
            'B' => 'badge-info',
            'C' => 'badge-warning',
            default => 'badge-danger',
        };
    }
}
