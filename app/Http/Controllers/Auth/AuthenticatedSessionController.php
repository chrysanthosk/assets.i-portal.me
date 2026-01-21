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
        $remember = $request->boolean('remember');

        /**
         * Capture where the user wanted to go BEFORE we authenticate.
         * This avoids the "I logged in and THEN it asked for 2FA" confusion.
         *
         * If there is already an intended URL (e.g., user was redirected to login
         * from a protected route), keep it. Otherwise default to dashboard.
         */
        if (!$request->session()->has('url.intended')) {
            // If they came directly to /login, previous() may be /login — so use dashboard.
            $prev = url()->previous();
            if (is_string($prev) && !str_ends_with($prev, '/login') && !str_ends_with($prev, '/two-factor')) {
                $request->session()->put('url.intended', $prev);
            } else {
                $request->session()->put('url.intended', route('dashboard', absolute: false));
            }
        }

        // Attempt login (logs user in if credentials are valid)
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        // If 2FA enabled -> force challenge BEFORE allowing access
        $twoFaEnabled = $user
            && (bool)($user->two_factor_enabled ?? false)
            && !empty($user->two_factor_secret);

        if ($twoFaEnabled) {
            // Store pending login info in session
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            // Ensure they are NOT authenticated until OTP is verified
            Auth::logout();

            // Rotate CSRF token but keep session data (2fa:user:id, url.intended)
            $request->session()->regenerateToken();

            return redirect()->route('2fa.challenge');
        }

        // No 2FA -> mark as passed for this session (middleware will allow)
        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
