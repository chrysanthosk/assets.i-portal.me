<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_and_confirm_two_factor(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => false]);

        $this->actingAs($user)->post(route('profile.2fa.enable'))->assertOk();

        $secret = session('2fa:setup:secret');
        $this->assertNotEmpty($secret);

        $otp = (new Google2FA)->getCurrentOtp($secret);

        $this->actingAs($user)
            ->post(route('profile.2fa.confirm'), ['code' => $otp])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertTrue((bool) $user->two_factor_enabled);
        $this->assertNotEmpty($user->two_factor_secret);
        $this->assertNotEmpty($user->two_factor_recovery_codes);
    }

    public function test_challenge_with_valid_otp_logs_in_and_sets_gate(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        // The 2FA gate stashes the pending user id before challenging.
        session(['2fa:user:id' => $user->id, '2fa:remember' => false]);

        $this->post(route('2fa.verify'), ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(session('2fa_passed') === true);
    }

    public function test_recovery_code_logs_in_and_is_single_use(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $codes = ['AAAA-BBBB-CCCC', 'DDDD-EEEE-FFFF'];

        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
        ]);

        session(['2fa:user:id' => $user->id, '2fa:remember' => false]);

        $this->post(route('2fa.verify'), ['code' => 'AAAA-BBBB-CCCC'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // The used code must be consumed, leaving exactly one remaining.
        $user->refresh();
        $remaining = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        $this->assertCount(1, $remaining);
        $this->assertNotContains('AAAA-BBBB-CCCC', $remaining);
    }

    public function test_challenge_with_invalid_code_does_not_authenticate(): void
    {
        $secret = (new Google2FA)->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        session(['2fa:user:id' => $user->id]);

        $this->from(route('2fa.challenge'))
            ->post(route('2fa.verify'), ['code' => '000000'])
            ->assertRedirect(route('2fa.challenge'));

        $this->assertGuest();
    }
}
