<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Show 2FA challenge form (user is NOT logged in yet in the corrected flow).
     */
    public function challengeForm(Request $request)
    {
        if (! $request->session()->has('2fa:user:id')) {
            return redirect()->route('login')->with('error', 'Your session expired. Please login again.');
        }

        return view('auth.two_factor');
    }

    /**
     * Verify TOTP code OR backup code and complete login.
     */
    public function challengeVerify(Request $request)
    {
        if (! $request->session()->has('2fa:user:id')) {
            return redirect()->route('login')->with('error', 'Your session expired. Please login again.');
        }

        $data = $request->validate([
            'code' => ['required','string','min:6','max:20'], // allow 6-digit totp OR longer backup code
        ]);

        $userId = (int) $request->session()->get('2fa:user:id');
        $remember = (bool) $request->session()->get('2fa:remember', false);

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget(['2fa:user:id','2fa:remember']);
            return redirect()->route('login')->with('error', 'User not found. Please login again.');
        }

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            Auth::login($user, $remember);
            $request->session()->forget(['2fa:user:id','2fa:remember']);
            $request->session()->put('2fa_passed', true);
            return redirect()->route('dashboard');
        }

        $input = trim($data['code']);

        // 1) Try TOTP (6 digits)
        $validTotp = false;
        if (preg_match('/^\d{6}$/', $input)) {
            $google2fa = new Google2FA();
            $secret = Crypt::decryptString($user->two_factor_secret);
            $validTotp = $google2fa->verifyKey($secret, $input);
        }

        // 2) Try backup code
        $validBackup = false;
        if (! $validTotp && ! empty($user->two_factor_recovery_codes)) {
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
            $normalized = strtoupper(str_replace([' ', '-'], '', $input));

            foreach ($codes as $idx => $code) {
                if ($normalized === strtoupper(str_replace([' ', '-'], '', $code))) {
                    $validBackup = true;

                    // consume used backup code
                    unset($codes[$idx]);
                    $user->two_factor_recovery_codes = encrypt(json_encode(array_values($codes)));
                    $user->save();
                    break;
                }
            }
        }

        if (! $validTotp && ! $validBackup) {
            throw ValidationException::withMessages([
                'code' => 'Invalid authentication code.',
            ]);
        }

        Auth::login($user, $remember);

        $request->session()->forget(['2fa:user:id','2fa:remember']);
        $request->session()->put('2fa_passed', true);

        return redirect()->route('dashboard');
    }

    /**
     * Start enabling 2FA: generate secret, store in session until confirm.
     * Also generate backup codes now and store them in session until confirm.
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $backupCodes = collect(range(1, 8))->map(function () {
            return strtoupper(Str::random(10));
        })->all();

        $request->session()->put('2fa_temp_secret', $secret);
        $request->session()->put('2fa_temp_backup_codes', $backupCodes);

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Portal'),
            $user->email ?? $user->username ?? 'user',
            $secret
        );

        return back()->with([
            '2fa_qr_url' => $qrUrl,
            '2fa_secret' => $secret,
            '2fa_backup_codes' => $backupCodes,
        ]);
    }

    /**
     * Confirm enabling 2FA with a valid TOTP code.
     * Persist secret + backup codes to DB.
     */
    public function confirm(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','digits:6'],
        ]);

        $user = $request->user();
        $secret = $request->session()->get('2fa_temp_secret');
        $backupCodes = $request->session()->get('2fa_temp_backup_codes', []);

        if (! $secret) {
            return back()->with('error', 'No pending 2FA setup found. Please enable again.');
        }

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($secret, $data['code'])) {
            return back()->with('error', 'Invalid code. Please try again.');
        }

        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->two_factor_enabled = true;
        $user->two_factor_recovery_codes = encrypt(json_encode(array_values($backupCodes)));
        $user->save();

        $request->session()->forget(['2fa_temp_secret','2fa_temp_backup_codes']);
        $request->session()->put('2fa_passed', true);

        // Show backup codes ONCE after confirm
        $request->session()->flash('2fa_show_backup_codes', true);
        $request->session()->flash('2fa_backup_codes', $backupCodes);

        return back()->with('success', '2FA enabled successfully. Store your backup codes now (shown once).');
    }

    public function disable(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'code' => ['required','digits:6'],
        ]);

        $user = $request->user();

        if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password)) {
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
        $user->two_factor_recovery_codes = null;
        $user->save();

        $request->session()->forget('2fa_passed');

        return back()->with('success', '2FA disabled successfully.');
    }
}
