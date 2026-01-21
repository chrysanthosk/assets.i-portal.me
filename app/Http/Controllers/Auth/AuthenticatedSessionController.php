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
        // Perform authentication (this logs the user in if valid)
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // If user has 2FA enabled, force challenge BEFORE allowing normal access
        $twoFaEnabled = (bool)($user->two_factor_enabled ?? false) && !empty($user->two_factor_secret);

        if ($twoFaEnabled) {
            // store pending login info
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $request->boolean('remember'));

            // IMPORTANT: logout so user is not considered authenticated yet
            Auth::logout();

            // Keep session but rotate token
            $request->session()->regenerateToken();

            return redirect()->route('2fa.challenge');
        }

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
