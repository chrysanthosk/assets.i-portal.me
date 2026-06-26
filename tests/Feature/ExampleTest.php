<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL is gated behind auth + 2FA, so guests are redirected to login.
     */
    public function test_guests_are_redirected_to_login_from_root(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
