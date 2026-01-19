<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->two_factor_enabled) {
            if (! $request->session()->get('2fa_passed', false)) {
                if (! $request->routeIs('2fa.challenge') && ! $request->routeIs('2fa.verify')) {
                    return redirect()->route('2fa.challenge');
                }
            }
        }

        return $next($request);
    }
}
