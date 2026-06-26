<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        // Clear any stale 2FA session from a previous attempt
        $request->session()->forget(['2fa:user:id', '2fa:remember', '2fa_passed']);

        $remember = $request->boolean('remember');

        // Authenticate (this logs the user in if valid)
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        $twoFaEnabled = $user
            && (bool) ($user->two_factor_enabled ?? false)
            && ! empty($user->two_factor_secret);

        if ($twoFaEnabled) {
            // Stash pending login, then logout. User must pass 2FA first.
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            Auth::logout();

            // Keep session alive but rotate token
            $request->session()->regenerateToken();

            return redirect()->route('2fa.challenge');
        }

        // No 2FA required
        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
