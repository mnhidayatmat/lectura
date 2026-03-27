<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Tenant extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('tenant')
            ->setDescriptionForEvent(fn (string $event) => "Tenant {$this->name} was {$event}");
    }

    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'primary_color',
        'timezone',
        'locale',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot(['role', 'student_id_number', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function isAiEnabled(): bool
    {
        return (bool) $this->getSetting('ai.enabled', true);
    }

    public function getAiProvider(): string
    {
        return $this->getSetting('ai.provider', config('lectura.ai.default_provider'));
    }

    public function getSubscriptionTier(): string
    {
        return $this->getSetting('subscription_tier', 'free');
    }

    public function isPro(): bool
    {
        return $this->getSubscriptionTier() === 'pro';
    }

    public function isFree(): bool
    {
        return ! $this->isPro();
    }

    public function getAiApiKey(string $provider): ?string
    {
        $encrypted = $this->getSetting("ai.api_keys.{$provider}");

        if (! $encrypted) {
            return null;
        }

        try {
            return decrypt($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setAiApiKey(string $provider, ?string $key): void
    {
        $settings = $this->settings ?? [];

        if ($key === null) {
            data_forget($settings, "ai.api_keys.{$provider}");
        } else {
            data_set($settings, "ai.api_keys.{$provider}", encrypt($key));
        }

        $this->update(['settings' => $settings]);
    }

    public function updateSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }
}
