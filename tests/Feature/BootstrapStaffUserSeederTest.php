<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BootstrapStaffUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class BootstrapStaffUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_admin_from_configured_credentials(): void
    {
        config([
            'services.helpdesk_staff.name' => 'Temporary Helpdesk Admin',
            'services.helpdesk_staff.email' => 'temporary.admin@example.com',
            'services.helpdesk_staff.password' => 'temporary-secret-123',
        ]);

        (new BootstrapStaffUserSeeder)->run();

        $admin = User::query()->where('email', 'temporary.admin@example.com')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->employment_status);
        $this->assertSame('approved', $admin->staff_approval_status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('temporary-secret-123', $admin->password));

        $this->post(route('login.store'), [
            'email' => 'temporary.admin@example.com',
            'password' => 'temporary-secret-123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_it_does_not_replace_credentials_when_reseeded(): void
    {
        config([
            'services.helpdesk_staff.name' => 'Temporary Helpdesk Admin',
            'services.helpdesk_staff.email' => 'temporary.admin@example.com',
            'services.helpdesk_staff.password' => 'temporary-secret-123',
        ]);

        (new BootstrapStaffUserSeeder)->run();

        config([
            'services.helpdesk_staff.name' => 'Changed Admin',
            'services.helpdesk_staff.password' => 'changed-secret-456',
        ]);

        (new BootstrapStaffUserSeeder)->run();

        $admin = User::query()->where('email', 'temporary.admin@example.com')->firstOrFail();

        $this->assertSame('Temporary Helpdesk Admin', $admin->name);
        $this->assertTrue(Hash::check('temporary-secret-123', $admin->password));
    }

    public function test_it_fails_on_a_fresh_production_database_without_admin_credentials(): void
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
}
