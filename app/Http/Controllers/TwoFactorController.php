<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * GET /two-factor
     * Must NOT require auth. Uses session('2fa:user:id')
     */
    public function challengeForm(Request $request)
    {
        if (!$request->session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two_factor');
    }

    /**
     * POST /two-factor
     * Verify OTP or recovery code, then log in user.
     */
    public function challengeVerify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $pendingUserId = $request->session()->get('2fa:user:id');
        $remember = (bool) $request->session()->get('2fa:remember', false);

        if (!$pendingUserId) {
            return redirect()->route('login');
        }

        $user = User::find($pendingUserId);

        if (
            !$user ||
            empty($user->two_factor_secret) ||
            !((bool)($user->two_factor_enabled ?? false))
        ) {
            $request->session()->forget(['2fa:user:id', '2fa:remember']);
            return redirect()->route('login')->withErrors([
                'code' => 'Invalid 2FA session. Please login again.'
            ]);
        }

        $code = trim((string) $request->input('code'));

        // Decrypt secret (supports legacy plain secrets too)
        $secret = $this->getDecryptedSecret($user->two_factor_secret);

        if (empty($secret)) {
            $request->session()->forget(['2fa:user:id', '2fa:remember']);
            return redirect()->route('login')->withErrors([
                'code' => '2FA secret is invalid. Please re-enable 2FA from profile.'
            ]);
        }

        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        // OTP check (allow small window)
        $otpOk = $google2fa->verifyKey($secret, $code, 2);

        // If not OTP, try recovery code
        $recoveryOk = false;
        if (!$otpOk) {
            $recoveryOk = $this->consumeRecoveryCode($user, $code);
        }

        if (!$otpOk && !$recoveryOk) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        // Finalize login
        Auth::login($user, $remember);

        // Mark 2FA passed for this session
        $request->session()->put('2fa_passed', true);

        // Cleanup pending keys
        $request->session()->forget(['2fa:user:id', '2fa:remember']);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * POST /profile/2fa/enable
     * Shows QR + secret, stores temp setup session only
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        $secret = $google2fa->generateSecretKey();
        $recovery = $this->generateRecoveryCodes();

        $request->session()->put('2fa:setup:secret', $secret);
        $request->session()->put('2fa:setup:recovery', $recovery);

        return view('auth.two_factor_setup', [
            'secret' => $secret,
            'recoveryCodes' => $recovery,
            'qrUrl' => $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email ?? $user->username ?? 'user',
                $secret
            ),
        ]);
    }

    /**
     * POST /profile/2fa/confirm
     * Confirms OTP and persists encrypted secret
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        $secret = $request->session()->get('2fa:setup:secret');
        $recovery = $request->session()->get('2fa:setup:recovery');

        if (!$secret || !$recovery) {
            return redirect()->route('profile.edit')
                ->withErrors(['two_factor' => '2FA setup session expired. Please enable again.']);
        }

        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        $ok = $google2fa->verifyKey($secret, trim((string)$request->input('code')), 2);

        if (!$ok) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        // IMPORTANT: store encrypted secret
        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->two_factor_enabled = true;
        $user->two_factor_recovery_codes = json_encode($recovery);
        $user->save();

        // show backup codes ONCE
        $request->session()->flash('2fa_show_backup_codes', true);
        $request->session()->flash('2fa_backup_codes', $recovery);

        $request->session()->forget(['2fa:setup:secret', '2fa:setup:recovery']);

        return redirect()->route('profile.edit')
            ->with('status', 'Two-factor authentication enabled.');
    }

    /**
     * POST /profile/2fa/disable
     */
    public function disable(Request $request)
    {
        $user = $request->user();

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        $request->session()->forget(['2fa_passed']);

        return redirect()->route('profile.edit')
            ->with('status', 'Two-factor authentication disabled.');
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(Str::random(10));
        }
        return $codes;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $stored = $user->two_factor_recovery_codes;
        if (!$stored) return false;

        $codes = json_decode($stored, true);
        if (!is_array($codes)) return false;

        $code = strtoupper(trim($code));
        $idx = array_search($code, $codes, true);
        if ($idx === false) return false;

        unset($codes[$idx]);
        $codes = array_values($codes);

        $user->two_factor_recovery_codes = json_encode($codes);
        $user->save();

        return true;
    }

    /**
     * Decrypt secret if encrypted, otherwise return as-is.
     */
    private function getDecryptedSecret(?string $value): ?string
    {
        if (!$value) return null;

        // Try decrypting (for values like "eyJpdiI6...")
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Not encrypted (legacy plain secret)
            return $value;
        }
    }
}
