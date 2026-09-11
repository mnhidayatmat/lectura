<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantContextController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');
        $user = $request->user();

        return response()->json([
            'data' => [
                'tenant' => new TenantResource($tenant),
                'roles' => $user->rolesInTenant($tenant->id),
                'active_role' => $user->roleInTenant($tenant->id),
                'is_pro' => $user->isPro(),
                'web_url' => url('/'.$tenant->slug.'/dashboard'),
            ],
        ]);
    }
}
