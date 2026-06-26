<?php

namespace App\Listeners;

use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;

class AuditAuthEvents
{
    public function handle(object $event): void
    {
        try {
            if ($event instanceof Login) {
                Audit::log('auth.login', $event->user, null, [
                    'guard' => $event->guard ?? 'web',
                ]);

                return;
            }

            if ($event instanceof Logout) {
                Audit::log('auth.logout', $event->user, null, [
                    'guard' => $event->guard ?? 'web',
                ]);

                return;
            }

            if ($event instanceof Failed) {
                // user can be null when email is wrong
                Audit::log('auth.login_failed', $event->user, null, [
                    'guard' => $event->guard ?? 'web',
                    'email' => $event->credentials['email'] ?? null,
                ]);

                return;
            }

            if ($event instanceof Registered) {
                Audit::log('auth.registered', $event->user, null, []);

                return;
            }

            if ($event instanceof PasswordReset) {
                Audit::log('auth.password_reset', $event->user, null, []);

                return;
            }
        } catch (\Throwable $e) {
            // never break the app for audit
        }
    }
}
