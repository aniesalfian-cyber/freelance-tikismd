<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = [
        'code', 'name', 'address', 'latitude', 'longitude',
        'radius_meters', 'is_main_office', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'is_main_office' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBranches($query)
    {
        return $query->where('is_main_office', false);
    }

    /** Kantor utama aktif yang menjadi titik presensi wajib. */
    public static function mainOffice(): ?self
    {
        return static::query()->where('is_main_office', true)->where('is_active', true)->first();
    }
}
