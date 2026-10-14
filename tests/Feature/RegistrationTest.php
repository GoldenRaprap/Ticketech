<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_requester_and_authenticates_them(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Jamie Student',
            'email' => 'jamie@example.edu',
            'password' => 'strong-password-123',
            'password_confirmation' => 'strong-password-123',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Jamie Student',
            'email' => 'jamie@example.edu',
            'role' => 'requester',
        ]);

        $user = User::where('email', 'jamie@example.edu')->firstOrFail();
        $this->assertTrue(Hash::check('strong-password-123', $user->password));
    }

    public function test_password_confirmation_is_required_for_registration(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Jamie Student',
            'email' => 'jamie@example.edu',
            'password' => 'strong-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
