<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_staff_sign_in_breadcrumb(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<div class="container public-topbar-inner">', false)
            ->assertSee('<a href="'.route('home').'">Go Back</a>', false)
            ->assertSee('<a href="'.route('home').'">PCU Helpdesk</a>', false)
            ->assertSee('<span aria-current="page">Staff Sign In</span>', false)
            ->assertSee('Philippine_Christian_University_logo.png')
            ->assertSee('<h1>Staff Sign In</h1>', false)
            ->assertSee(route('register'))
            ->assertSee('Register for staff access')
            ->assertSee('Sign in with your PCU staff account.')
            ->assertDontSee('Staff sessions are shared across tabs');
    }

    public function test_requester_cannot_sign_in_to_staff_admin_portal(): void
    {
        $user = User::factory()->create([
            'email' => 'jamie@example.edu',
            'password' => 'correct-password',
            'role' => 'requester',
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'jamie@example.edu',
            'password' => 'correct-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);
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
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));

        $requester = User::factory()->create(['role' => 'requester']);
        $this->actingAs($requester)->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_admin_routes_reject_guests_and_requesters(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $requester = User::factory()->create(['role' => 'requester']);
        $this->actingAs($requester)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_inactive_admin_is_logged_out_when_accessing_admin_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'employment_status' => 'retired',
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_staff_cannot_access_admin_controls(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.users'))->assertForbidden();
    }

    public function test_admin_can_access_admin_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee('admin-console-page', false)
            ->assertSee('Manage staff access');
        $this->actingAs($admin)->get(route('admin.moderation'))->assertOk()
            ->assertSee('admin-console-page', false)
            ->assertSee('Pending reports');
        $this->actingAs($admin)->get(route('admin.sla-settings'))->assertOk()
            ->assertSee('admin-console-page', false)
            ->assertSee('Service level targets')
            ->assertSee('Save SLA targets');
        $this->actingAs($admin)->get(route('admin.audit-logs'))->assertOk()
            ->assertSee('admin-console-page', false)
            ->assertSee('System events')
            ->assertSee('data-sidebar-icon="audit-logs"', false);
        $this->actingAs($admin)->get(route('admin.system-settings'))->assertOk()
            ->assertSee('admin-console-page', false)
            ->assertSee('Helpdesk identity');
        $this->actingAs($admin)->get(route('staff.dashboard'))->assertOk();

        $this->get(route('admin.dashboard'))->assertDontSee('Categories');
    }

    public function test_staff_and_admin_can_sign_out_from_the_sidebar_account_control(): void
    {
        $staff = User::factory()->create(['name' => 'Staff Member', 'role' => 'staff']);
        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Staff Member')
            ->assertSee('Staff Account')
            ->assertSee('<form class="sidebar-logout"', false)
            ->assertDontSee('<form class="header-logout"', false);

        $admin = User::factory()->create(['name' => 'Admin Member', 'role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin Member')
            ->assertSee('Admin Account')
            ->assertSee('<form class="sidebar-logout"', false)
            ->assertDontSee('<form class="header-logout"', false);
    }

    public function test_categories_admin_page_is_no_longer_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/categories')->assertNotFound();
    }

    public function test_staff_cannot_access_admin_audit_logs(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_staff_can_sign_in_to_staff_portal(): void
    {
        $staff = User::factory()->create([
            'email' => 'staff@example.edu',
            'password' => 'correct-password',
            'role' => 'staff',
        ]);

        $this->post(route('login.store'), [
            'email' => 'staff@example.edu',
            'password' => 'correct-password',
        ])->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_local_preview_sign_in_opens_the_staff_workspace(): void
    {
        config()->set('app.env', 'local');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('Staff (temporary)')
            ->assertSee('Admin (temporary)');

        $this->post(route('login.preview', ['role' => 'staff']))
            ->assertRedirect(route('staff.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'local-preview-staff@pcu.test',
            'role' => 'staff',
        ]);
        $this->assertAuthenticated();
        $this->get(route('staff.dashboard'))->assertOk();
    }

    public function test_local_preview_sign_in_is_unavailable_outside_local_environment(): void
    {
        $this->post(route('login.preview', ['role' => 'staff']))->assertNotFound();
        $this->assertGuest();
    }

    public function test_local_admin_preview_opens_the_admin_dashboard(): void
    {
        config()->set('app.env', 'local');

        $this->post(route('login.preview', ['role' => 'admin']))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'local-preview-admin@pcu.test',
            'role' => 'admin',
        ]);
        $this->assertAuthenticated();
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_sign_in_opens_admin_console(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.edu',
            'password' => 'correct-password',
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin@example.edu',
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_duplicate_staff_login_route_is_removed(): void
    {
        $this->get('/staff/login')->assertNotFound();
    }
}
