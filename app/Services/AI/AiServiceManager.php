<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\MockProvider;

class AiServiceManager
{
    protected ?AiProviderInterface $provider = null;

    public function resolveProvider(?string $providerName = null): AiProviderInterface
    {
        if ($this->provider) {
            return $this->provider;
        }

        $name = $providerName ?? $this->getTenantProvider();

        // Check tenant-owned API key first (Pro), then global env key
        $tenantKey = $this->getTenantApiKey($name);

        $this->provider = match ($name) {
            'claude' => ($tenantKey || $this->hasApiKey('ANTHROPIC_API_KEY'))
                ? $this->makeClaudeProvider($tenantKey)
                : new MockProvider(),
            'openai' => ($tenantKey || $this->hasApiKey('OPENAI_API_KEY'))
                ? $this->makeOpenAiProvider($tenantKey)
                : new MockProvider(),
            'gemini' => ($tenantKey || $this->hasApiKey('GEMINI_API_KEY'))
                ? $this->makeGeminiProvider($tenantKey)
                : new MockProvider(),
            default => new MockProvider(),
        };

        return $this->provider;
    }

    public function resetProvider(): void
    {
        $this->provider = null;
    }

    public function complete(string $prompt, array $options = []): array
    {
        $provider = $this->resolveProvider();
        $startTime = microtime(true);

        try {
            $result = $provider->complete($prompt, $options);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logUsage($provider, $result, 'success', $durationMs, $options);

            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logUsage($provider, [], 'failed', $durationMs, $options);

            throw $e;
        }
    }

    protected function logUsage(AiProviderInterface $provider, array $result, string $status, int $durationMs, array $options): void
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (! $tenant || ! auth()->check()) {
            return;
        }

        AiUsageLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'course_id' => $options['course_id'] ?? null,
            'module' => $options['module'] ?? 'general',
            'provider' => $provider->getName(),
            'model' => $provider->getModel(),
            'input_tokens' => $result['input_tokens'] ?? 0,
            'output_tokens' => $result['output_tokens'] ?? 0,
            'response_status' => $status,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }

    protected function getTenantProvider(): string
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->getSetting('ai.provider') ?? config('lectura.ai.default_provider', 'claude');
    }

    protected function getTenantApiKey(string $provider): ?string
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (! $tenant || ! $tenant->isPro()) {
            return null;
        }

        return $tenant->getAiApiKey($provider);
    }

    protected function hasApiKey(string $envKey): bool
    {
        return ! empty(env($envKey));
    }

    /**
     * Get a configured AI provider from the database.
     * Returns the provider matching the given name, or the default provider.
     */
    public function getDbProvider(?string $providerType = null): ?AiProvider
    {
        if ($providerType) {
            return AiProvider::active()
                ->where('provider_type', $providerType)
                ->first();
        }

        return AiProvider::getDefault();
    }

    /**
     * Get API key from DB provider, falling back to env config.
     */
    protected function resolveApiKey(string $providerName, ?string $tenantKey): ?string
    {
        if ($tenantKey) {
            return $tenantKey;
        }

        // Check database providers
        $providerType = match ($providerName) {
            'claude' => 'anthropic',
            'openai' => 'openai',
            'gemini' => 'google',
            default => $providerName,
        };

        $dbProvider = $this->getDbProvider($providerType);
        if ($dbProvider?->hasApiKey()) {
            return $dbProvider->getDecryptedApiKey();
        }

        // Fall back to env
        return match ($providerName) {
            'claude' => env('ANTHROPIC_API_KEY'),
            'openai' => env('OPENAI_API_KEY'),
            'gemini' => env('GEMINI_API_KEY'),
            default => null,
        };
    }

    protected function makeClaudeProvider(?string $apiKey = null): AiProviderInterface
    {
        // Will be implemented when connecting real API
        // $apiKey ?? config('lectura.ai.providers.claude.api_key')
        return new MockProvider();
    }

    protected function makeOpenAiProvider(?string $apiKey = null): AiProviderInterface
    {
        return new MockProvider();
    }

    protected function makeGeminiProvider(?string $apiKey = null): AiProviderInterface
    {
        return new MockProvider();
    }
}
