<?php

namespace App\Providers;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

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
    }
}
