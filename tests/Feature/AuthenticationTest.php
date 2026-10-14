<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'jamie@example.edu',
            'password' => 'correct-password',
            'role' => 'requester',
        ]);

        $this->post(route('login.store'), [
            'email' => 'jamie@example.edu',
            'password' => 'correct-password',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_incorrect_password_does_not_authenticate(): void
    {
        User::factory()->create(['email' => 'jamie@example.edu']);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'jamie@example.edu',
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_staff_routes_reject_guests_and_requesters(): void
    {
        $this->get(route('staff.dashboard'))->assertRedirect(route('staff.login'));

        $requester = User::factory()->create(['role' => 'requester']);
        $this->actingAs($requester)->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_staff_can_sign_in_to_staff_portal(): void
    {
        $staff = User::factory()->create([
            'email' => 'staff@example.edu',
            'password' => 'correct-password',
            'role' => 'admin',
        ]);

        $this->post(route('staff.login.store'), [
            'email' => 'staff@example.edu',
            'password' => 'correct-password',
        ])->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticatedAs($staff);
    }
}
