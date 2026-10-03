<?php

namespace Slimpay\School\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Slimpay\School\LookupHandler;
use Slimpay\School\WebhookHandler;

class SlimpayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/slimpay.php', 'slimpay');

        $this->app->singleton(LookupHandler::class, function ($app) {
            $c = $app['config']['slimpay'];

            // Student and invoice lookups use separate secrets, so each gets its own handler.
            return new LookupHandler(
                (string) ($c['student_lookup_secret'] ?? ''),
                !empty($c['student_lookup']) ? $app->make($c['student_lookup']) : null,
                null,
                (int) $c['tolerance'],
            );
        });

        $this->app->singleton('slimpay.invoice-lookup', function ($app) {
            $c = $app['config']['slimpay'];

            return new LookupHandler(
                (string) ($c['invoice_lookup_secret'] ?? ''),
                null,
                !empty($c['invoice_lookup']) ? $app->make($c['invoice_lookup']) : null,
                (int) $c['tolerance'],
            );
        });

        $this->app->singleton(\Slimpay\School\Client::class, fn ($app) => new \Slimpay\School\Client(
            (string) $app['config']['slimpay.api_key'],
            (string) $app['config']['slimpay.base_url'],
        ));

        $this->app->singleton(WebhookHandler::class, function ($app) {
            $c = $app['config']['slimpay'];
            return new WebhookHandler($c['webhook_secret'] ?? null, (int) $c['tolerance']);
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../../config/slimpay.php' => config_path('slimpay.php')], 'slimpay-config');

        if (config('slimpay.routes.enabled')) {
            Route::prefix(config('slimpay.routes.prefix'))
                ->middleware(config('slimpay.routes.middleware', []))
                ->group(function () {
                    Route::post('student-lookup', [SlimpayController::class, 'student'])->name('slimpay.student-lookup');
                    Route::post('invoice-lookup', [SlimpayController::class, 'invoice'])->name('slimpay.invoice-lookup');
                    Route::post('webhook', [SlimpayController::class, 'webhook'])->name('slimpay.webhook');
                });
        }
    }
}
