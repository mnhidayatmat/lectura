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

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar_url',
        'locale',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
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

        return $this->tenantUsers()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->value('role');
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
}
