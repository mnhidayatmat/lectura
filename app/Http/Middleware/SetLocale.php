<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if (in_array($locale, ['en', 'ms'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        // Priority: user preference > tenant default > system default
        if ($user = $request->user()) {
            if ($user->locale) {
                return $user->locale;
            }
        }

        if ($tenant = app('current_tenant')) {
            if ($tenant->locale) {
                return $tenant->locale;
            }
        }

        return config('app.locale', 'en');
    }
}
