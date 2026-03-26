<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenant\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        protected TenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if (! $tenant) {
            abort(404, 'Institution not found.');
        }

        app()->instance('current_tenant', $tenant);

        session(['current_tenant_id' => $tenant->id]);

        return $next($request);
    }
}
