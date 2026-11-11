<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BootstrapStaffUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class BootstrapStaffUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_a_verified_admin_from_configured_credentials(): void
    {
        config([
            'services.helpdesk_staff.name' => 'Temporary Helpdesk Admin',
            'services.helpdesk_staff.email' => 'temporary.admin@example.com',
            'services.helpdesk_staff.password' => 'temporary-secret-123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'temporary.admin@example.com')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('temporary-secret-123', $admin->password));

        $this->post(route('login.store'), [
            'email' => 'temporary.admin@example.com',
            'password' => 'temporary-secret-123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_seeding_does_not_change_an_existing_admin(): void
    {
        $admin = User::factory()->create([
            'name' => 'Existing Admin',
            'email' => 'existing.admin@example.com',
            'password' => 'existing-password-123',
            'role' => 'admin',
        ]);
        config([
            'services.helpdesk_staff.email' => 'different.admin@example.com',
            'services.helpdesk_staff.password' => 'temporary-secret-123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Existing Admin', $admin->fresh()->name);
        $this->assertTrue(Hash::check('existing-password-123', $admin->fresh()->password));
    }

    public function test_seeding_fails_in_production_when_first_admin_credentials_are_missing(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.helpdesk_staff.email' => null,
            'services.helpdesk_staff.password' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Set HELPDESK_STAFF_EMAIL and HELPDESK_STAFF_PASSWORD');

        (new BootstrapStaffUserSeeder)->run();
    }

    public function test_seeding_rejects_a_password_shorter_than_twelve_characters(): void
    {
        config([
            'services.helpdesk_staff.email' => 'temporary.admin@example.com',
            'services.helpdesk_staff.password' => 'too-short',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HELPDESK_STAFF_EMAIL must be valid and HELPDESK_STAFF_PASSWORD must be at least 12 characters.');

        (new BootstrapStaffUserSeeder)->run();
    }

    public function test_seeding_fails_if_admin_email_is_already_used_by_another_account(): void
    {
        User::factory()->create([
            'email' => 'temporary.admin@example.com',
            'role' => 'requester',
        ]);
        config([
            'services.helpdesk_staff.email' => 'temporary.admin@example.com',
            'services.helpdesk_staff.password' => 'temporary-secret-123',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belongs to an existing non-admin account');

        (new BootstrapStaffUserSeeder)->run();
    }
}
