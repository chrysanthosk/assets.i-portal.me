<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $remember = $request->boolean('remember');

        // Attempt login
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // If 2FA enabled => force challenge BEFORE allowing access
        $twoFaEnabled = (bool)($user->two_factor_enabled ?? false) && !empty($user->two_factor_secret);

        if ($twoFaEnabled) {
            // store pending user id + remember flag
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            // IMPORTANT: log out now so they are not authenticated yet
            Auth::logout();

            // ensure session persists
            $request->session()->regenerateToken();

            return redirect()->route('2fa.challenge');
        }

        // No 2FA => proceed
        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
