<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless tenant resolution for /api/v1/t/{tenant}/... routes.
 *
 * Binds `current_tenant` before route-model binding runs (see middleware priority in
 * bootstrap/app.php) so BelongsToTenant scopes apply to bound models, then removes the
 * `tenant` parameter so API controller methods only receive their own parameters.
 * The optional X-Lectura-Role header replaces the web role switcher's session value.
 */
class ResolveApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $slug = $route->parameter('tenant');

        $tenant = is_string($slug)
            ? Tenant::where('slug', $slug)->where('is_active', true)->first()
            : null;

        if (! $tenant) {
            return response()->json(['message' => 'Institution not found.'], 404);
        }

        $user = $request->user();

        if (! $user->is_super_admin && ! $user->belongsToTenant($tenant->id)) {
            return response()->json(['message' => 'You do not have access to this institution.'], 403);
        }

        app()->instance('current_tenant', $tenant);
        $route->forgetParameter('tenant');

        $roleKey = "tenant_{$tenant->id}_role";
        $role = $request->header('X-Lectura-Role');

        if ($role) {
            if (! $user->is_super_admin && ! in_array($role, $user->rolesInTenant($tenant->id), true)) {
                return response()->json(['message' => "You do not have the {$role} role."], 403);
            }

            session()->put($roleKey, $role);
        } else {
            session()->forget($roleKey);
        }

        $locale = $user->locale ?: $tenant->locale;

        if (in_array($locale, ['en', 'ms'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
