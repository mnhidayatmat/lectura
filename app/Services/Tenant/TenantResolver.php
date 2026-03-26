<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $resolver = config('lectura.tenant.resolver', 'path');

        return match ($resolver) {
            'subdomain' => $this->resolveFromSubdomain($request),
            'path' => $this->resolveFromPath($request),
            default => null,
        };
    }

    protected function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) < 3) {
            return null;
        }

        $slug = $parts[0];

        return Tenant::where('slug', $slug)->where('is_active', true)->first();
    }

    protected function resolveFromPath(Request $request): ?Tenant
    {
        $slug = $request->route('tenant');

        if (! $slug) {
            return null;
        }

        if ($slug instanceof Tenant) {
            return $slug->is_active ? $slug : null;
        }

        return Tenant::where('slug', $slug)->where('is_active', true)->first();
    }
}
