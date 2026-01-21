<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TODELETETwoFactorController extends Controller
{
    public function challengeForm(Request $request)
    {
        return view('auth.two_factor');
    }

    public function challengeVerify(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','digits:6'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            // No 2FA enabled, allow
            $request->session()->put('2fa_passed', true);
            return redirect()->route('dashboard');
        }

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($user->two_factor_secret);

        $valid = $google2fa->verifyKey($secret, $data['code']);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'Invalid authentication code.',
            ]);
        }

        $request->session()->put('2fa_passed', true);

        return redirect()->route('dashboard');
    }

    public function enable(Request $request)
    {
        $user = $request->user();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Store temporarily in session until user confirms with code
        $request->session()->put('2fa_temp_secret', $secret);

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Portal'),
            $user->email ?? $user->username,
            $secret
        );

        return back()->with([
            '2fa_qr_url' => $qrUrl,
            '2fa_secret' => $secret,
        ]);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','digits:6'],
        ]);

        $user = $request->user();
        $secret = $request->session()->get('2fa_temp_secret');

        if (! $secret) {
            return back()->with('error', 'No pending 2FA setup found. Please enable again.');
        }

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($secret, $data['code'])) {
            return back()->with('error', 'Invalid code. Please try again.');
        }

        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->two_factor_enabled = true;
        $user->save();

        $request->session()->forget('2fa_temp_secret');
        $request->session()->put('2fa_passed', true);

        return back()->with('success', '2FA enabled successfully.');
    }

    public function disable(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'code' => ['required','digits:6'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            return back()->with('error', '2FA is not enabled.');
        }

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($user->two_factor_secret);

        if (! $google2fa->verifyKey($secret, $data['code'])) {
            return back()->with('error', 'Invalid authentication code.');
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->save();

        $request->session()->forget('2fa_passed');

        return back()->with('success', '2FA disabled successfully.');
    }
}
