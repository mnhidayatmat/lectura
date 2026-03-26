<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

interface AiProviderInterface
{
    public function complete(string $prompt, array $options = []): array;

    public function getName(): string;

    public function getModel(): string;
}
