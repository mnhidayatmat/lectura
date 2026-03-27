<?php

declare(strict_types=1);

namespace App\Services\ActiveLearning;

use App\Models\Tenant;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TierGateService
{
    public function assertProFeature(?User $user, string $featureName): void
    {
        if (! $user || $user->isFree()) {
            throw new HttpException(402, __('active_learning.pro_required', ['feature' => $featureName]));
        }
    }

    public function canUseAiGeneration(?User $user, Tenant $tenant): bool
    {
        return $user && $user->isPro() && $tenant->isAiEnabled();
    }

    public function canUseAiGrouping(?User $user, Tenant $tenant): bool
    {
        return $user && $user->isPro() && $tenant->isAiEnabled();
    }

    public function canManageAiKeys(?User $user): bool
    {
        return $user && $user->isPro();
    }
}
