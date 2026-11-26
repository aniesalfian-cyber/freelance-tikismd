<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const STATUS_ON_TIME = 'hadir_tepat';
    public const STATUS_LATE = 'terlambat';
    public const STATUS_INVALID = 'invalid_radius';
    public const STATUS_LEAVE = 'cuti';
    public const STATUS_PERMISSION = 'izin';
    public const STATUS_SICK = 'sakit';
    public const STATUS_OFF = 'libur';
    public const STATUS_ABSENT = 'alfa';

    public const STATUS_LABELS = [
        self::STATUS_ON_TIME => 'Hadir Tepat Waktu',
        self::STATUS_LATE => 'Terlambat',
        self::STATUS_INVALID => 'Di Luar Radius',
        self::STATUS_LEAVE => 'Cuti',
        self::STATUS_PERMISSION => 'Izin',
        self::STATUS_SICK => 'Sakit',
        self::STATUS_OFF => 'Libur Shift',
        self::STATUS_ABSENT => 'Belum Presensi',
    ];

    public const VALIDATION_LABELS = [
        'VALID' => 'Terverifikasi',
        'CAPTURED' => 'Terekam Otomatis',
        'REVIEW' => 'Menunggu Review',
        'REJECTED' => 'Ditolak HRD',
        'MANUAL_ADJUSTMENT' => 'Koreksi HRD',
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
            'scheduled_check_in' => 'datetime',
            'scheduled_check_out' => 'datetime',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_in_accuracy' => 'float',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'check_out_accuracy' => 'float',
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

    public function hasCheckedIn(): bool
    {
        return $this->check_in_at !== null;
    }

    public function isNonWorkingLeave(): bool
    {
        return ! $this->hasCheckedIn() && in_array($this->status, [
            self::STATUS_LEAVE,
            self::STATUS_PERMISSION,
            self::STATUS_SICK,
            self::STATUS_OFF,
        ], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_ON_TIME => 'badge-success',
            self::STATUS_LATE => 'badge-warning',
            self::STATUS_LEAVE, self::STATUS_PERMISSION, self::STATUS_SICK, self::STATUS_OFF => 'badge-info',
            default => 'badge-danger',
        };
    }

    public function validationLabel(): string
    {
        $raw = (string) ($this->validation_status ?? '');

        return self::VALIDATION_LABELS[$raw] ?? ($raw !== '' ? $raw : 'Terverifikasi');
    }

    public function validationBadge(): string
    {
        return match ($this->validation_status ?? null) {
            'VALID', 'MANUAL_ADJUSTMENT' => 'badge-success',
            'CAPTURED' => 'badge-info',
            'REVIEW' => 'badge-warning',
            'REJECTED' => 'badge-danger',
            default => 'badge-muted',
        };
    }

    public function officeDisplayName(): string
    {
        if (! empty($this->location_name)) {
            return (string) $this->location_name;
        }

        return $this->relationLoaded('outlet') && $this->outlet
            ? $this->outlet->name
            : 'Kantor HSM';
    }

    public function officeRadiusMeters(): int
    {
        if (isset($this->office_radius_meters) && $this->office_radius_meters !== null) {
            return (int) $this->office_radius_meters;
        }

        return (int) ($this->relationLoaded('outlet') && $this->outlet ? $this->outlet->radius_meters : 25);
    }

    public function isWithinCheckInRadius(): bool
    {
        if (isset($this->check_in_within_radius) && $this->check_in_within_radius !== null) {
            return (bool) $this->check_in_within_radius;
        }
        if ($this->check_in_distance_meters === null) {
            return true;
        }

        return (float) $this->check_in_distance_meters <= $this->officeRadiusMeters();
    }

    public function formattedDistance(?float $meters): string
    {
        if ($meters === null) {
            return '-';
        }

        return ($meters < 100 && fmod($meters, 1.0) !== 0.0)
            ? number_format($meters, 1, '.', '').' m'
            : round($meters).' m';
    }

    public function radiusSummary(): string
    {
        if ($this->check_in_distance_meters === null) {
            return 'Radius '.$this->officeRadiusMeters().' m';
        }

        return $this->formattedDistance((float) $this->check_in_distance_meters).' / Radius '.$this->officeRadiusMeters().' m';
    }

    public function checkInPhotoUrl(): ?string
    {
        if (! empty($this->hsm_attendance_id) && ! empty($this->check_in_photo_path)) {
            return route('media.hsm-attendance-photo', [
                'attendanceId' => (int) $this->hsm_attendance_id,
                'type' => 'in',
            ]);
        }

        return null;
    }

    public function checkOutPhotoUrl(): ?string
    {
        if (! empty($this->hsm_attendance_id) && ! empty($this->check_out_photo_path)) {
            return route('media.hsm-attendance-photo', [
                'attendanceId' => (int) $this->hsm_attendance_id,
                'type' => 'out',
            ]);
        }

        return null;
    }

    public function workMinutes(): ?int
    {
        if (! $this->check_in_at || ! $this->check_out_at) {
            return null;
        }

        return (int) max(0, $this->check_in_at->diffInMinutes($this->check_out_at, false));
    }

    public function workDurationFormatted(): ?string
    {
        $minutes = $this->workMinutes();
        if ($minutes === null) {
            return null;
        }

        return intdiv($minutes, 60).'j '.($minutes % 60).'m';
    }
}
