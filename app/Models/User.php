<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_super_admin', 'is_pro'])
            ->logOnlyDirty()
            ->useLogName('user')
            ->setDescriptionForEvent(fn (string $event) => "User {$this->name} was {$event}");
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar_url',
        'locale',
        'is_super_admin',
        'is_pro',
        'drive_access_token',
        'drive_refresh_token',
        'drive_token_expires_at',
        'drive_root_folder_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'drive_access_token',
        'drive_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_pro' => 'boolean',
            'drive_token_expires_at' => 'datetime',
        ];
    }

    public function isDriveConnected(): bool
    {
        return $this->drive_refresh_token !== null;
    }

    // ── Tenant Relationships ──

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot(['role', 'student_id_number', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function sections(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_students')
            ->withPivot(['enrolled_at', 'enrollment_method', 'is_active'])
            ->wherePivot('is_active', true);
    }

    // ── Subscription Helpers ──

    public function isPro(): bool
    {
        return (bool) $this->is_pro;
    }

    public function isFree(): bool
    {
        return ! $this->isPro();
    }

    // ── Tenant Helpers ──

    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }

    public function roleInTenant(?int $tenantId): ?string
    {
        if (! $tenantId) {
            return null;
        }

        // Check for session-based role override (role switcher)
        $sessionRole = session("tenant_{$tenantId}_role");
        if ($sessionRole) {
            return $sessionRole;
        }

        // Super admins default to admin role in any tenant
        if ($this->is_super_admin) {
            return 'admin';
        }

        return $this->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->value('role');
    }

    public function rolesInTenant(int $tenantId): array
    {
        $roles = $this->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('role')
            ->toArray();

        // Super admins always have admin role
        if ($this->is_super_admin && ! in_array('admin', $roles)) {
            array_unshift($roles, 'admin');
        }

        return $roles;
    }

    public function hasRoleInTenant(int $tenantId, array|string $roles): bool
    {
        $roles = (array) $roles;

        return $this->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->exists();
    }

    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.is_active', true);
    }

    public function attendanceExcuses(): HasMany
    {
        return $this->hasMany(AttendanceExcuse::class);
    }

    public function attendanceWarnings(): HasMany
    {
        return $this->hasMany(AttendanceWarning::class);
    }
}
