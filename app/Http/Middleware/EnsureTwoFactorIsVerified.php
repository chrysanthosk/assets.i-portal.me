<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only enforce when 2FA is truly configured
        $requires2fa = $user
            && (bool) ($user->two_factor_enabled ?? false)
            && ! empty($user->two_factor_secret);

        if (! $requires2fa) {
            return $next($request);
        }

        // Allow the challenge endpoints without 2fa_passed
        if ($request->routeIs('2fa.challenge') || $request->routeIs('2fa.verify')) {
            return $next($request);
        }

        // If already verified in this session, allow
        if ($request->session()->get('2fa_passed', false) === true) {
            return $next($request);
        }

        // Enforce: set intended URL, stash pending user, then logout (so user is NOT authenticated yet)
        $request->session()->put('url.intended', $request->fullUrl());
        $request->session()->put('2fa:user:id', $user->id);
        $request->session()->put('2fa:remember', false);

        Auth::logout();
        $request->session()->regenerateToken();

        return redirect()->route('2fa.challenge');
    }
}
