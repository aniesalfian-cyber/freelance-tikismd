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
        self::ROLE_FREELANCER => 'Freelancer',
        self::ROLE_OWNER => 'Owner',
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
