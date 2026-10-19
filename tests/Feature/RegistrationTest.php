<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_requester_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Jamie Student',
            'email' => 'jamie@example.edu',
            'password' => 'strong-password-123',
            'password_confirmation' => 'strong-password-123',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }
}
