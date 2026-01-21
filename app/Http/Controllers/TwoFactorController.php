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
     * Show challenge form after login when 2FA is enabled but not yet verified.
     */
    public function challengeForm(Request $request)
    {
        // If no pending 2FA user in session, go to login
        if (!$request->session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two_factor');
    }

    /**
     * POST /two-factor
     * Verify the submitted OTP and complete login.
     */
    public function challengeVerify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:6'],
        ]);

<<<<<<< HEAD
        $pendingUserId = $request->session()->get('2fa:user:id');
=======
        $userId = $request->session()->get('2fa:user:id');
>>>>>>> b047e10 (updates)
        $remember = (bool) $request->session()->get('2fa:remember', false);

        if (!$userId) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

<<<<<<< HEAD
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
=======
        if (!$user->two_factor_enabled || empty($user->two_factor_secret)) {
            // If user disabled 2FA while pending, just login
            Auth::login($user, $remember);
            $request->session()->forget(['2fa:user:id', '2fa:remember']);
            return redirect()->intended(route('dashboard'));
        }

        $google2fa = new Google2FA();

        $secret = $this->decrypt2faSecret($user->two_factor_secret);

        $code = preg_replace('/\s+/', '', (string) $request->input('code'));

        if (!$google2fa->verifyKey($secret, $code)) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
>>>>>>> b047e10 (updates)
        }

        Auth::login($user, $remember);

        // Mark 2FA verified in session
        $request->session()->put('2fa:verified', true);
        $request->session()->forget(['2fa:user:id', '2fa:remember']);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * POST /profile/2fa/enable
<<<<<<< HEAD
     * Shows QR + secret, stores temp setup session only
=======
     * Start 2FA setup (generate secret + recovery codes, show QR + confirmation form)
>>>>>>> b047e10 (updates)
     */
    public function enable(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

<<<<<<< HEAD
        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        $secret = $google2fa->generateSecretKey();
=======
        // Already enabled
        if ($user->two_factor_enabled && !empty($user->two_factor_secret)) {
            return redirect()->route('profile.edit')->with('status', '2FA is already enabled.');
        }

        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey(32);

        // Recovery codes (simple + readable)
>>>>>>> b047e10 (updates)
        $recovery = $this->generateRecoveryCodes();

        // Store temporarily in session until confirm step
        $request->session()->put('2fa:setup:secret', $secret);
        $request->session()->put('2fa:setup:recovery', $recovery);

        // IMPORTANT: use getQRCodeUrl() (exists) instead of getQRCodeInline() (missing)
        $label = $user->email ?? $user->username ?? 'user';
        $qrUrl = $google2fa->getQRCodeUrl(config('app.name'), $label, $secret);

        // Show setup screen (QR + code confirm)
        return view('auth.two_factor_setup', [
            'secret' => $secret,
            'recoveryCodes' => $recovery,
            'qrUrl' => $qrUrl,
        ]);
    }

    /**
     * POST /profile/2fa/confirm
<<<<<<< HEAD
     * Confirms OTP and persists encrypted secret
=======
     * Confirm OTP then persist encrypted secret + recovery codes.
>>>>>>> b047e10 (updates)
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $secret = $request->session()->get('2fa:setup:secret');
        $recovery = $request->session()->get('2fa:setup:recovery');

        if (!$secret || !$recovery) {
            return redirect()->route('profile.edit')
                ->withErrors(['two_factor' => '2FA setup session expired. Please try enabling again.']);
        }

<<<<<<< HEAD
        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        $ok = $google2fa->verifyKey($secret, trim((string)$request->input('code')), 2);
=======
        $google2fa = new Google2FA();
>>>>>>> b047e10 (updates)

        $code = preg_replace('/\s+/', '', (string) $request->input('code'));

        if (!$google2fa->verifyKey($secret, $code)) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

<<<<<<< HEAD
        // IMPORTANT: store encrypted secret
        $user->two_factor_secret = Crypt::encryptString($secret);
=======
>>>>>>> b047e10 (updates)
        $user->two_factor_enabled = true;
        $user->two_factor_secret = $this->encrypt2faSecret($secret);
        $user->two_factor_recovery_codes = $this->encryptRecoveryCodes($recovery);
        $user->save();

        // Show recovery codes once
        $request->session()->forget(['2fa:setup:secret', '2fa:setup:recovery']);
        $request->session()->flash('2fa_show_backup_codes', true);
        $request->session()->flash('2fa_backup_codes', $recovery);

        return redirect()->route('profile.edit')->with('status', '2FA enabled successfully.');
    }

    /**
     * POST /profile/2fa/disable
     */
    public function disable(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'code' => ['required', 'string', 'max:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!Auth::validate(['email' => $user->email, 'password' => $request->input('current_password')]) &&
            !Auth::validate(['username' => $user->username, 'password' => $request->input('current_password')])) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        if (!$user->two_factor_enabled || empty($user->two_factor_secret)) {
            return redirect()->route('profile.edit')->with('status', '2FA is already disabled.');
        }

        $google2fa = new Google2FA();

        $secret = $this->decrypt2faSecret($user->two_factor_secret);
        $code = preg_replace('/\s+/', '', (string) $request->input('code'));

        if (!$google2fa->verifyKey($secret, $code)) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        // Also clear any “verified” session
        $request->session()->forget(['2fa:verified']);

        return redirect()->route('profile.edit')->with('status', '2FA disabled successfully.');
    }

    // ---------------------------
    // Helpers
    // ---------------------------

    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // Example: ABCD-EFGH-IJKL
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    private function encrypt2faSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    private function decrypt2faSecret(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    private function encryptRecoveryCodes(array $codes): string
    {
        return Crypt::encryptString(json_encode(array_values($codes)));
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
