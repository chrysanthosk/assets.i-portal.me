<?php

namespace App\Providers;

use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // During development, avoid stale permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
                'email' => $event->credentials['email'] ?? null,
            ]);
        });
    }
}
