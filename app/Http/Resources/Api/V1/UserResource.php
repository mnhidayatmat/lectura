<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    private const ROLE_ORDER = ['admin', 'coordinator', 'lecturer', 'student'];

    public function toArray(Request $request): array
    {
        $memberships = $this->tenantUsers()
            ->where('is_active', true)
            ->with('tenant')
            ->get()
            ->filter(fn (TenantUser $membership) => $membership->tenant?->is_active)
            ->groupBy('tenant_id')
            ->map(fn ($rows) => [
                'tenant' => new TenantResource($rows->first()->tenant),
                'roles' => $rows->pluck('role')
                    ->unique()
                    ->sortBy(fn (string $role) => array_search($role, self::ROLE_ORDER, true))
                    ->values(),
                'student_id_number' => $rows->pluck('student_id_number')->filter()->first(),
            ])
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'locale' => $this->locale,
            // Google-only accounts confirm destructive actions by email, not password.
            'has_password' => $this->password !== null,
            'is_pro' => $this->isPro(),
            'is_super_admin' => (bool) $this->is_super_admin,
            'memberships' => $memberships,
        ];
    }
}
