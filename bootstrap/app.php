<?php

use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\ResolveApiTenant;
use App\Http\Middleware\ResolveCourseContext;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        // routes/channels.php was never registered, so private/presence channels had
        // no /broadcasting/auth endpoint and their authorization callbacks never ran.
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.access' => EnsureTenantAccess::class,
            'locale' => SetLocale::class,
            'api.tenant' => ResolveApiTenant::class,
            'course.context' => ResolveCourseContext::class,
        ]);

        // Mobile API: bind the tenant before route-model binding so tenant scopes apply to bound models
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveApiTenant::class);

        // Same for the web. Without this, SubstituteBindings resolves models while
        // `current_tenant` is still unbound, BelongsToTenant's global scope no-ops,
        // and another institution's record binds happily — an admin could delete a
        // semester belonging to a different institution. ResolveTenant still sorts
        // after StartSession, which it needs because it writes `current_tenant_id`
        // to the session.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);

        // Rate limit every API route (see RouteServiceProvider's `api` limiter)
        $middleware->throttleApi();

        // MCP + OAuth endpoints — no CSRF cookie needed
        $middleware->validateCsrfTokens(except: [
            '/mcp',
            '/mcp/*',
            '/authorize',
            '/oauth/*',
            '/.well-known/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Mobile API: don't expose "No query results for model [...]" to app users
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'That item could not be found. It may have been removed.'], 404);
            }
        });
    })->create();
