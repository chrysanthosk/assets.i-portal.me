<?php

namespace App\Providers;

use App\Listeners\AuditAuthEvents;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            AuditAuthEvents::class,
        ],
        Logout::class => [
            AuditAuthEvents::class,
        ],
        Failed::class => [
            AuditAuthEvents::class,
        ],
        Registered::class => [
            AuditAuthEvents::class,
        ],
        PasswordReset::class => [
            AuditAuthEvents::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
