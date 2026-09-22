<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

require_once app_path('helpers.php');

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\AI\AiServiceManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Brevo API mail transport
        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('brevo+api', function (array $config) {
                return new BrevoApiTransport($config['key']);
            });
        });

        // Mobile API limit. Headroom for the app's polling (quiz state 2s, chat 4s,
        // QR token 5s, live hub 15s) while still capping abuse; per user, else per IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Signed-out mobile endpoints (login, register, Google/Apple, password reset).
        // Each endpoint has its own budget, and the tight one is per email, so a
        // lecture hall behind one campus NAT can still sign in together.
        RateLimiter::for('mobile-auth', function (Request $request) {
            $endpoint = $request->route()?->getName() ?? $request->path();
            $email = Str::lower(trim((string) $request->input('email')));
            $limits = [Limit::perMinute(60)->by($endpoint.'|'.$request->ip())];

            if ($email !== '') {
                $limits[] = Limit::perMinute(10)->by($endpoint.'|'.$email.'|'.$request->ip());
            }

            return $limits;
        });
    }
}
