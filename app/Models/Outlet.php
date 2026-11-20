<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    public const TYPE_GERAI = 'gerai';
    public const TYPE_CORPORATE = 'corporate';

    public const TYPE_LABELS = [
        self::TYPE_GERAI => 'Gerai Cabang',
        self::TYPE_CORPORATE => 'Perusahaan Rekan (Corporate)',
    ];

    protected $fillable = [
        'code', 'name', 'type', 'address', 'latitude', 'longitude',
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

    public function isCorporate(): bool
    {
        return $this->type === self::TYPE_CORPORATE;
    }

    public function isGerai(): bool
    {
        return $this->type !== self::TYPE_CORPORATE;
    }

    public function typeLabel(): string
    {
        if ($this->is_main_office) {
            return 'Kantor Utama';
        }

        return $this->isCorporate() ? 'Corporate (Rekan)' : 'Gerai Cabang';
    }

    public function typeBadge(): string
    {
        if ($this->is_main_office) {
            return 'badge-orange';
        }

        return $this->isCorporate() ? 'badge-primary' : 'badge-info';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBranches($query)
    {
        return $query->where('is_main_office', false);
    }

    public function scopeGerai($query)
    {
        return $query->where('type', self::TYPE_GERAI);
    }

    public function scopeCorporate($query)
    {
        return $query->where('type', self::TYPE_CORPORATE);
    }

    /** Kantor utama aktif yang menjadi titik presensi wajib. */
    public static function mainOffice(): ?self
    {
        return static::query()->where('is_main_office', true)->where('is_active', true)->first();
    }
}
