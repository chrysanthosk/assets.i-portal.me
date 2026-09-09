<?php

use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind the reverse proxy (Docker network / Nginx Proxy Manager) so
        // HTTPS detection, HSTS and secure cookies see the real scheme.
        $middleware->trustProxies(at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1']);

        // Apply security headers to all web responses.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            // Your custom middleware
            '2fa' => EnsureTwoFactorIsVerified::class,

            // Spatie Permission middleware (required for routes like permission:view_dashboard)
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report exceptions to Sentry when a DSN is configured (inert otherwise).
        Integration::handles($exceptions);
    })
    ->create();
