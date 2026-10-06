<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\TenantContextController;
use App\Http\Controllers\Api\V1\WatchMediaController;
use Illuminate\Support\Facades\Route;

// ── Mobile app API (Lectura Go) — Sanctum bearer tokens ──
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:mobile-auth')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('/auth/google/exchange', [AuthController::class, 'googleExchange'])->name('auth.google.exchange');
        Route::post('/auth/apple', [AuthController::class, 'apple'])->name('auth.apple');
    });

    // Episode media for the Watch screens. The pre-signed URL is the authorization
    // (players and image widgets send no bearer token). No API throttle: a whole
    // class behind one campus IP seeks through videos at once.
    Route::middleware('signed:relative')->withoutMiddleware('throttle:api')->prefix('watch')->name('watch.')->group(function () {
        Route::get('/episodes/{episode}/stream', [WatchMediaController::class, 'stream'])->name('episodes.stream');
        Route::get('/episodes/{episode}/poster', [WatchMediaController::class, 'poster'])->name('episodes.poster');
        Route::get('/series/{series}/cover', [WatchMediaController::class, 'cover'])->name('series.cover');
        Route::get('/captions/{caption}', [WatchMediaController::class, 'caption'])->name('captions');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::delete('/me', [AuthController::class, 'destroy'])->name('me.destroy');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::post('/devices', [DeviceTokenController::class, 'store'])->name('devices.store');
        Route::delete('/devices', [DeviceTokenController::class, 'destroy'])->name('devices.destroy');

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
