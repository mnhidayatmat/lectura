<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app('current_tenant');
        $user = $request->user();

        if (! $tenant || ! $user) {
            abort(403, 'Unauthorized access.');
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (! $user->belongsToTenant($tenant->id)) {
            abort(403, 'You do not have access to this institution.');
        }

        return $next($request);
    }
}
