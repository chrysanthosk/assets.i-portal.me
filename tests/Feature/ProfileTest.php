<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_profile_name_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/name', [
                'name' => 'Updated',
                'surname' => 'Person',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated', $user->name);
        $this->assertSame('Person', $user->surname);
    }

    public function test_email_change_request_sends_an_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/email/request', ['new_email' => 'new@example.com'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertNotNull($user->email_otp_hash);
        Mail::assertSent(\App\Mail\EmailChangeOtpMail::class);
    }

    public function test_password_can_be_updated_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'password',
                'password' => 'NewStr0ng!Pass99',
                'password_confirmation' => 'NewStr0ng!Pass99',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewStr0ng!Pass99', $user->refresh()->password));
    }

    public function test_password_is_not_updated_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewStr0ng!Pass99',
                'password_confirmation' => 'NewStr0ng!Pass99',
            ]);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
