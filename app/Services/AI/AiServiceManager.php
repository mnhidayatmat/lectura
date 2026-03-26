<?php

declare(strict_types=1);

namespace App\Services\AI;

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

        // For MVP, use mock provider if no API keys configured
        $this->provider = match ($name) {
            'claude' => $this->hasApiKey('ANTHROPIC_API_KEY') ? $this->makeClaudeProvider() : new MockProvider(),
            'openai' => $this->hasApiKey('OPENAI_API_KEY') ? $this->makeOpenAiProvider() : new MockProvider(),
            default => new MockProvider(),
        };

        return $this->provider;
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

    protected function hasApiKey(string $envKey): bool
    {
        return ! empty(env($envKey));
    }

    protected function makeClaudeProvider(): AiProviderInterface
    {
        // Will be implemented when connecting real API
        return new MockProvider();
    }

    protected function makeOpenAiProvider(): AiProviderInterface
    {
        return new MockProvider();
    }
}
