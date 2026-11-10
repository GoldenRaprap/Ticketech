<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_the_first_verified_active_super_admin(): void
    {
        $this->artisan('app:bootstrap-super-admin', [
            'name' => 'Initial Helpdesk Admin',
            'email' => 'initial.admin@pcu.edu.ph',
        ])
            ->expectsQuestion('Password (minimum 12 characters)', 'secure-admin-password')
            ->expectsQuestion('Confirm password', 'secure-admin-password')
            ->expectsOutput('initial.admin@pcu.edu.ph is now the initial Super Admin.')
            ->assertSuccessful();

        $superAdmin = User::query()->where('email', 'initial.admin@pcu.edu.ph')->firstOrFail();

        $this->assertSame('admin', $superAdmin->role);
        $this->assertSame('active', $superAdmin->employment_status);
        $this->assertSame('approved', $superAdmin->staff_approval_status);
        $this->assertNotNull($superAdmin->email_verified_at);
    }

    public function test_command_refuses_to_bootstrap_when_a_super_admin_already_exists(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->artisan('app:bootstrap-super-admin')
            ->expectsOutput('A Super Admin account already exists. Bootstrap is only available before the first Super Admin is created.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_command_rejects_a_weak_initial_password(): void
    {
        $this->artisan('app:bootstrap-super-admin', [
            'name' => 'Initial Helpdesk Admin',
            'email' => 'initial.admin@pcu.edu.ph',
        ])
            ->expectsQuestion('Password (minimum 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->expectsOutput('The password field must be at least 12 characters.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
