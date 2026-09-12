<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\TenantContextController;
use Illuminate\Support\Facades\Route;

// ── Mobile app API (Lectura Go) — Sanctum bearer tokens ──
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('/auth/google/exchange', [AuthController::class, 'googleExchange'])->name('auth.google.exchange');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::delete('/me', [AuthController::class, 'destroy'])->name('me.destroy');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/tenants', [OnboardingController::class, 'tenants'])->name('tenants');
        Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding');

        // Tenant-scoped: `api.tenant` binds current_tenant and removes the {tenant} parameter
        Route::prefix('t/{tenant}')->middleware('api.tenant')->name('tenant.')->group(function () {
            Route::get('/context', [TenantContextController::class, 'show'])->name('context');

            require __DIR__.'/api/v1/common.php';
            require __DIR__.'/api/v1/student.php';
            require __DIR__.'/api/v1/lecturer.php';
            require __DIR__.'/api/v1/live.php';
            require __DIR__.'/api/v1/workspace.php';
        });
    });
});
