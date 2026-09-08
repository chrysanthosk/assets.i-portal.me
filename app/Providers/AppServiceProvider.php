<?php

namespace App\Providers;

use App\Support\Audit;
use App\Support\Deeds\ClaudeDeedExtractor;
use App\Support\Deeds\DeedExtractor;
use App\Support\MailConfig;
use App\Support\Portal;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Title deed OCR/extraction backend (tests bind a fake)
        $this->app->bind(DeedExtractor::class, ClaudeDeedExtractor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // When running behind a reverse proxy / port mapping (e.g. Docker
        // publishes :8080 -> container :80), force generated URLs to use
        // APP_URL as their root so the public scheme/host/port are preserved.
        if (config('app.force_root_url') && config('app.url')) {
            URL::forceRootUrl(config('app.url'));

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Route all outgoing mail through the SMTP server configured in the UI.
        MailConfig::apply();

        // @advanced ... @endadvanced — markup only shown when advanced mode is on
        Blade::if('advanced', fn () => Portal::advanced());

        /**
         * IMPORTANT:
         * Never clear Spatie permission cache on every request in production.
         * It causes unnecessary DB/cache load and can trigger failures during boot.
         *
         * If you ever need to reset permissions cache in production, do it explicitly:
         *   php artisan permission:cache-reset
         * or:
         *   php artisan optimize:clear
         */
        if (App::environment(['local', 'testing'])) {
            // Best-effort only: must never crash boot when the cache/DB backend
            // is not reachable yet (fresh install, migrations not run, etc.).
            try {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
                // ignore — permissions cache will refresh on its own / via optimize:clear
            }
        }

        // -----------------------------
        // Audit: Auth events
        // -----------------------------
        Event::listen(Login::class, function (Login $event) {
            Audit::log('auth.login', $event->user, null, [
                'guard' => $event->guard ?? 'web',
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            Audit::log('auth.logout', $event->user, null, [
                'guard' => $event->guard ?? 'web',
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            Audit::log('auth.login_failed', $event->user, null, [
                'guard' => $event->guard ?? 'web',
                // You authenticate by username, not email
                'username' => $event->credentials['username'] ?? null,
            ]);
        });
    }
}
