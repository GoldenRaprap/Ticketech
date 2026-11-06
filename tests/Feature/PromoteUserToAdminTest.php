<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteUserToAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_promotes_a_verified_account_to_administrator(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@pcu.edu.ph',
            'role' => 'requester',
        ]);

        $this->artisan('users:promote-admin', ['email' => $user->email])
            ->assertSuccessful()
            ->expectsOutput('operator@pcu.edu.ph now has administrator access.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
            'staff_approval_status' => 'approved',
        ]);
    }

    public function test_command_rejects_an_unverified_account(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@pcu.edu.ph',
            'role' => 'requester',
        ]);

        $this->artisan('users:promote-admin', ['email' => $user->email])
            ->assertFailed()
            ->expectsOutput('Only an active account with a verified email can be promoted.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'requester',
        ]);
    }
}
