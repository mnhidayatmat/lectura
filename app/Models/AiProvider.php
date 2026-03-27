<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiProvider extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'provider_type',
        'api_key',
        'api_base_url',
        'model',
        'max_tokens',
        'temperature',
        'top_p',
        'timeout_seconds',
        'is_active',
        'is_default',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'max_tokens' => 'integer',
            'temperature' => 'float',
            'top_p' => 'float',
            'timeout_seconds' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'settings' => 'array',
        ];
    }

    // ── API Key Encryption ──

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getDecryptedApiKey(): ?string
    {
        if (! $this->attributes['api_key']) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['api_key']);
        } catch (\Exception) {
            return null;
        }
    }

    public function getMaskedApiKey(): ?string
    {
        $key = $this->getDecryptedApiKey();

        if (! $key) {
            return null;
        }

        $length = strlen($key);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($key, 0, 4) . str_repeat('*', $length - 8) . substr($key, -4);
    }

    public function hasApiKey(): bool
    {
        return ! empty($this->attributes['api_key']);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // ── Helpers ──

    public static function getDefault(): ?self
    {
        return static::active()->default()->first()
            ?? static::active()->first();
    }

    public static function getProviderTypes(): array
    {
        return [
            'anthropic' => 'Anthropic (Claude)',
            'openai' => 'OpenAI',
            'google' => 'Google (Gemini)',
            'custom' => 'Custom / OpenAI-Compatible',
        ];
    }

    public static function getDefaultModels(): array
    {
        return [
            'anthropic' => [
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (Recommended)',
                'claude-opus-4-6' => 'Claude Opus 4.6',
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
                'claude-sonnet-4-5-20250514' => 'Claude Sonnet 4.5',
            ],
            'openai' => [
                'gpt-4o' => 'GPT-4o (Recommended)',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'o3-mini' => 'o3-mini',
            ],
            'google' => [
                'gemini-2.0-flash' => 'Gemini 2.0 Flash (Recommended)',
                'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            ],
            'custom' => [],
        ];
    }
}
