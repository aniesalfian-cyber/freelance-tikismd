<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_HRD = 'admin_hrd';
    public const ROLE_FREELANCER = 'freelancer';
    public const ROLE_OWNER = 'owner';

    public const ROLES = [
        self::ROLE_SUPERADMIN => 'SuperAdmin',
        self::ROLE_HRD => 'Admin HRD / Supervisor',
        self::ROLE_FREELANCER => 'Karyawan',
        self::ROLE_OWNER => 'Owner',
    ];

    // 4 Divisi Resmi Operasional Kurir PT. HSM
    public const DIVISION_R4_DELIVERY = 'R4 Kurier Delivery';
    public const DIVISION_R4_GERAI_CORP = 'R4 Gerai, Corporate & Delivery';
    public const DIVISION_R2_DELIVERY = 'R2 Delivery';
    public const DIVISION_R2_PICKUP_CORP = 'R2 Pickup Corporate';

    public const DIVISIONS = [
        self::DIVISION_R4_DELIVERY => [
            'name' => 'R4 Kurier Delivery',
            'code' => 'R4-DEL',
            'vehicle' => 'Mobil Box (Roda 4)',
            'vehicle_short' => 'R4 Mobil Box',
            'vehicle_type' => 'R4',
            'scope' => 'Hanya bertugas untuk pengantaran paket (Delivery)',
            'scope_short' => 'Hanya Pengantaran',
            'activities' => ['delivery'],
            'badge' => 'badge-info',
            'icon' => 'truck',
        ],
        self::DIVISION_R4_GERAI_CORP => [
            'name' => 'R4 Gerai, Corporate & Delivery',
            'code' => 'R4-GCD',
            'vehicle' => 'Mobil Box (Roda 4)',
            'vehicle_short' => 'R4 Mobil Box',
            'vehicle_type' => 'R4',
            'scope' => 'Bertugas pickup dan delivery baik dari gerai atau corporate rekanan TIKISMD',
            'scope_short' => 'Pickup & Delivery (Gerai & Corporate)',
            'activities' => ['pickup', 'delivery'],
            'badge' => 'badge-orange',
            'icon' => 'truck',
        ],
        self::DIVISION_R2_DELIVERY => [
            'name' => 'R2 Delivery',
            'code' => 'R2-DEL',
            'vehicle' => 'Sepeda Motor (Roda 2)',
            'vehicle_short' => 'R2 Motor',
            'vehicle_type' => 'R2',
            'scope' => 'Hanya bertugas untuk pengantaran paket ke penerima',
            'scope_short' => 'Hanya Pengantaran',
            'activities' => ['delivery'],
            'badge' => 'badge-success',
            'icon' => 'bike',
        ],
        self::DIVISION_R2_PICKUP_CORP => [
            'name' => 'R2 Pickup Corporate',
            'code' => 'R2-PC',
            'vehicle' => 'Sepeda Motor (Roda 2)',
            'vehicle_short' => 'R2 Motor',
            'vehicle_type' => 'R2',
            'scope' => 'Hanya bertugas pickup paket dari perusahaan rekanan TIKISMD',
            'scope_short' => 'Hanya Pickup Perusahaan Mitra',
            'activities' => ['pickup'],
            'badge' => 'badge-warning',
            'icon' => 'package',
        ],
    ];

    protected $fillable = [
        'nik', 'name', 'email', 'phone', 'password', 'role', 'avatar',
        'specialization', 'hourly_rate', 'is_active', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'last_login_at' => 'datetime',
        ];
    }

    // ---- Relations ----

    public function tasks(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function performanceLogs(): HasMany
    {
        return $this->hasMany(PerformanceLog::class);
    }

    // ---- Scopes ----

    public function scopeFreelancers($query)
    {
        return $query->where('role', self::ROLE_FREELANCER);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDivision($query, string $division)
    {
        return $query->where('specialization', $division);
    }

    // ---- Helpers ----

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isFreelancer(): bool
    {
        return $this->role === self::ROLE_FREELANCER;
    }

    /** SuperAdmin & Admin HRD dapat mengelola data master & tugas. */
    public function canManage(): bool
    {
        return $this->hasRole(self::ROLE_SUPERADMIN, self::ROLE_HRD);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function divisionInfo(): ?array
    {
        return self::DIVISIONS[$this->specialization] ?? null;
    }

    public function divisionName(): string
    {
        return $this->specialization ?: 'Operasional Kurir';
    }

    public function vehicleName(): string
    {
        return $this->divisionInfo()['vehicle'] ?? (str_starts_with((string)$this->specialization, 'R4') ? 'Mobil Box (R4)' : 'Sepeda Motor (R2)');
    }

    public function vehicleShort(): string
    {
        return $this->divisionInfo()['vehicle_short'] ?? (str_starts_with((string)$this->specialization, 'R4') ? 'R4 Box' : 'R2 Motor');
    }

    public function operationalScope(): string
    {
        return $this->divisionInfo()['scope'] ?? ($this->specialization ?: 'Operasional Kurir');
    }

    public function operationalScopeShort(): string
    {
        return $this->divisionInfo()['scope_short'] ?? 'Kurir';
    }

    public function divisionBadge(): string
    {
        return $this->divisionInfo()['badge'] ?? 'badge-muted';
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPERADMIN => route('admin.dashboard'),
            self::ROLE_HRD => route('hrd.dashboard'),
            self::ROLE_FREELANCER => route('pwa.workspace'),
            self::ROLE_OWNER => route('owner.dashboard'),
            default => route('login'),
        };
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = collect($parts)->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');

        return $letters ?: '?';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }
}
