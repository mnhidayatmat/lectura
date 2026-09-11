<?php

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
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'api.tenant' => \App\Http\Middleware\ResolveApiTenant::class,
        ]);

        // Mobile API: bind the tenant before route-model binding so tenant scopes apply to bound models
        $middleware->prependToPriorityList(SubstituteBindings::class, \App\Http\Middleware\ResolveApiTenant::class);

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
