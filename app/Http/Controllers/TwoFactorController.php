<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Session key set once a user has cleared the 2FA gate for this session.
     * Must match App\Http\Middleware\EnsureTwoFactorIsVerified.
     */
    private const VERIFIED_KEY = '2fa_passed';

    /**
     * GET /two-factor
     * Show the challenge form after login when 2FA is enabled but not verified.
     */
    public function challengeForm(Request $request)
    {
        // If no pending 2FA user in session, go back to login.
        if (!$request->session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two_factor');
    }

    /**
     * POST /two-factor
     * Verify the submitted OTP (or recovery code) and complete login.
     */
    public function challengeVerify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:19'],
        ]);

        $userId  = $request->session()->get('2fa:user:id');
        $remember = (bool) $request->session()->get('2fa:remember', false);

        if (!$userId) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        // If the user disabled 2FA while the challenge was pending, just log in.
        if (!((bool) ($user->two_factor_enabled ?? false)) || empty($user->two_factor_secret)) {
            Auth::login($user, $remember);
            $request->session()->put(self::VERIFIED_KEY, true);
            $request->session()->forget(['2fa:user:id', '2fa:remember']);

            return redirect()->intended(route('dashboard'));
        }

        $code   = trim((string) $request->input('code'));
        $secret = $this->getDecryptedSecret($user->two_factor_secret);

        if (empty($secret)) {
            $request->session()->forget(['2fa:user:id', '2fa:remember']);

            return redirect()->route('login')->withErrors([
                'code' => '2FA secret is invalid. Please re-enable 2FA from your profile.',
            ]);
        }

        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        // Try the rolling OTP first (allow a small clock-drift window), then
        // fall back to a one-time recovery code.
        $otpCode  = preg_replace('/\s+/', '', $code);
        $otpOk    = strlen($otpCode) <= 6 && $google2fa->verifyKey($secret, $otpCode, 2);
        $recovery = $otpOk ? false : $this->consumeRecoveryCode($user, $code);

        if (!$otpOk && !$recovery) {
            Audit::log('2fa.challenge_failed', $user);

            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        Auth::login($user, $remember);

        $request->session()->put(self::VERIFIED_KEY, true);
        $request->session()->forget(['2fa:user:id', '2fa:remember']);

        Audit::log($recovery ? '2fa.recovery_code_used' : '2fa.verified', $user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * POST /profile/2fa/enable
     * Generate a secret + recovery codes and show the QR / confirmation form.
     * Nothing is persisted until the confirm step succeeds.
     */
    public function enable(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (($user->two_factor_enabled ?? false) && !empty($user->two_factor_secret)) {
            return redirect()->route('profile.edit')->with('status', '2FA is already enabled.');
        }

        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        $secret   = $google2fa->generateSecretKey(32);
        $recovery = $this->generateRecoveryCodes();

        // Hold the pending setup in the session until confirmation.
        $request->session()->put('2fa:setup:secret', $secret);
        $request->session()->put('2fa:setup:recovery', $recovery);

        $label = $user->email ?? $user->username ?? 'user';
        $qrUrl = $google2fa->getQRCodeUrl(config('app.name'), $label, $secret);

        return view('auth.two_factor_setup', [
            'secret'        => $secret,
            'recoveryCodes' => $recovery,
            'qrUrl'         => $qrUrl,
        ]);
    }

    /**
     * POST /profile/2fa/confirm
     * Confirm the OTP, then persist the encrypted secret + recovery codes.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $secret   = $request->session()->get('2fa:setup:secret');
        $recovery = $request->session()->get('2fa:setup:recovery');

        if (!$secret || !$recovery) {
            return redirect()->route('profile.edit')
                ->withErrors(['two_factor' => '2FA setup session expired. Please try enabling again.']);
        }

        /** @var Google2FA $google2fa */
        $google2fa = app(Google2FA::class);

        $code = preg_replace('/\s+/', '', (string) $request->input('code'));

        if (!$google2fa->verifyKey($secret, $code, 2)) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $user->two_factor_enabled         = true;
        $user->two_factor_secret          = $this->encrypt2faSecret($secret);
        $user->two_factor_recovery_codes  = $this->encryptRecoveryCodes($recovery);
        $user->save();

        // Show the recovery codes exactly once.
        $request->session()->forget(['2fa:setup:secret', '2fa:setup:recovery']);
        $request->session()->flash('2fa_show_backup_codes', true);
        $request->session()->flash('2fa_backup_codes', $recovery);

        Audit::log('2fa.enabled', $user);

        return redirect()->route('profile.edit')->with('status', '2FA enabled successfully.');
    }

    /**
     * POST /profile/2fa/disable
     */
    public function disable(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'code'             => ['required', 'string', 'max:19'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $passwordOk = Auth::validate(['email' => $user->email, 'password' => $request->input('current_password')])
            || Auth::validate(['username' => $user->username, 'password' => $request->input('current_password')]);

        if (!$passwordOk) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        if (!($user->two_factor_enabled ?? false) || empty($user->two_factor_secret)) {
            return redirect()->route('profile.edit')->with('status', '2FA is already disabled.');
        }

        $secret = $this->getDecryptedSecret($user->two_factor_secret);
        $code   = trim((string) $request->input('code'));

        $otpCode = preg_replace('/\s+/', '', $code);
        $otpOk   = strlen($otpCode) <= 6 && app(Google2FA::class)->verifyKey((string) $secret, $otpCode, 2);

        // A valid OTP or recovery code is required to disable.
        if (!$otpOk && !$this->consumeRecoveryCode($user, $code)) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $user->two_factor_enabled        = false;
        $user->two_factor_secret         = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        $request->session()->forget([self::VERIFIED_KEY]);

        Audit::log('2fa.disabled', $user);

        return redirect()->route('profile.edit')->with('status', '2FA disabled successfully.');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // e.g. ABCD-EFGH-IJKL
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
        }

        return $codes;
    }

    /**
     * Validate a recovery code and, if valid, consume it (single use).
     */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        $codes = $this->getRecoveryCodes($user);
        if (empty($codes)) {
            return false;
        }

        $remaining = array_values(array_filter(
            $codes,
            static fn ($stored) => !hash_equals(strtoupper((string) $stored), $code)
        ));

        // No code was removed -> the supplied code did not match.
        if (count($remaining) === count($codes)) {
            return false;
        }

        $user->two_factor_recovery_codes = $this->encryptRecoveryCodes($remaining);
        $user->save();

        return true;
    }

    private function getRecoveryCodes(User $user): array
    {
        $raw = $user->two_factor_recovery_codes;
        if (empty($raw)) {
            return [];
        }

        try {
            $json = Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            // Legacy / already-plain JSON.
            $json = $raw;
        }

        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encrypt2faSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    private function encryptRecoveryCodes(array $codes): string
    {
        return Crypt::encryptString(json_encode(array_values($codes)));
    }

    /**
     * Decrypt a secret if it is encrypted, otherwise return it as-is
     * (supports legacy plain-text secrets).
     */
    private function getDecryptedSecret(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
