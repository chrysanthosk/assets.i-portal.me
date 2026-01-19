<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required','string'],
            'password' => ['required','string'],
        ]);

        // Attempt auth by username
        $ok = Auth::attempt(
            ['username' => $request->input('username'), 'password' => $request->input('password')],
            $request->boolean('remember')
        );

        if (!$ok) {
            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // Reset 2FA passed flag every new login
        $request->session()->forget('2fa_passed');

        // If user has 2FA enabled, force challenge
        if ($request->user()->two_factor_enabled) {
            return redirect()->route('2fa.show');
        }

        // Otherwise mark 2FA as passed for consistency
        $request->session()->put('2fa_passed', true);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
