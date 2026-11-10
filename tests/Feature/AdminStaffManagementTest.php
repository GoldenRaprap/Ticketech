<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_multiple_active_staff_accounts_and_email_password_setup_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Notification::fake();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'staff' => [
                ['name' => 'First Hire', 'email' => 'first.hire@pcu.edu.ph'],
                ['name' => 'Second Hire', 'email' => 'second.hire@pcu.edu.ph'],
            ],
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', '2 staff accounts created and approved. Password setup links have been emailed.');

        $firstHire = User::query()->where('email', 'first.hire@pcu.edu.ph')->firstOrFail();
        $secondHire = User::query()->where('email', 'second.hire@pcu.edu.ph')->firstOrFail();

        foreach ([$firstHire, $secondHire] as $staff) {
            $this->assertSame('staff', $staff->role);
            $this->assertSame('active', $staff->employment_status);
            $this->assertSame('approved', $staff->staff_approval_status);
            $this->assertNull($staff->email_verified_at);
            $this->assertFalse(Hash::check('password12345', $staff->password));
            Notification::assertSentTo($staff, ResetPassword::class);
            $this->assertDatabaseHas('ticket_events', [
                'actor_id' => $admin->id,
                'event' => 'staff_account_created',
                'subject_type' => 'User',
                'subject_id' => (string) $staff->id,
            ]);
        }

        $setupToken = null;
        Notification::assertSentTo(
            $firstHire,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$setupToken): bool {
                $setupToken = $notification->token;

                return true;
            },
        );

        $this->post(route('password.update'), [
            'token' => $setupToken,
            'email' => $firstHire->email,
            'password' => 'new-staff-password',
            'password_confirmation' => 'new-staff-password',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($firstHire->fresh()->email_verified_at);

        $this->post(route('login.store'), [
            'email' => $firstHire->email,
            'password' => 'new-staff-password',
        ])->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticatedAs($firstHire->fresh());
    }

    public function test_bulk_staff_creation_rejects_duplicate_and_existing_emails_without_partial_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'existing@pcu.edu.ph']);
        Notification::fake();

        $this->actingAs($admin)->from(route('admin.users'))->post(route('admin.users.store'), [
            'staff' => [
                ['name' => 'New Hire', 'email' => 'new.hire@pcu.edu.ph'],
                ['name' => 'Existing User', 'email' => 'existing@pcu.edu.ph'],
                ['name' => 'Duplicate Hire', 'email' => 'NEW.HIRE@PCU.EDU.PH'],
            ],
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors(['staff.1.email', 'staff.2.email']);

        $this->assertDatabaseMissing('users', ['email' => 'new.hire@pcu.edu.ph']);
        Notification::assertNothingSent();
    }

    public function test_admin_can_deactivate_staff_with_a_button_and_reactivate_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'password' => 'correct-password',
            'employment_status' => 'active',
        ]);
        $ticket = $this->createTicketForStaff($staff);
        $previousRememberToken = $staff->remember_token;

        $this->actingAs($admin)->patch(route('admin.users.status.update', $staff), [
            'employment_status' => 'inactive',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $staff->name.' has been deactivated.');

        $staff->refresh();
        $this->assertSame('inactive', $staff->employment_status);
        $this->assertNotSame($previousRememberToken, $staff->remember_token);
        $this->assertModelExists($staff);
        $this->assertNull($ticket->fresh()->assigned_to_user_id);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'actor_id' => $admin->id,
            'event' => 'assignment_changed',
            'description' => "Assignment removed because {$staff->name}'s access was deactivated.",
        ]);

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)->patch(route('admin.users.status.update', $staff), [
            'employment_status' => 'active',
        ])->assertRedirect(route('admin.users'));

        $this->post(route('login.store'), [
            'email' => $staff->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($staff->refresh());
        $this->assertSame('active', $staff->employment_status);
    }

    public function test_staff_cannot_add_or_manage_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $anotherStaff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('admin.users.store'), [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@pcu.edu.ph',
        ])->assertForbidden();

        $this->actingAs($staff)->patch(route('admin.users.status.update', $anotherStaff), [
            'employment_status' => 'inactive',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@pcu.edu.ph']);
        $this->assertSame('active', $anotherStaff->refresh()->employment_status);
    }

    public function test_empty_pending_approvals_are_collapsed_after_the_staff_directory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('<details class="admin-pending-empty">', false)
            ->assertDontSee('<details class="admin-pending-empty" open>', false)
            ->assertSeeInOrder(['Manage Super Admins, department admins and staff', 'Pending staff approvals', 'No pending staff applications']);
    }

    public function test_admin_user_directory_lists_accounts_and_filters_by_access_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $activeStaff = User::factory()->create([
            'name' => 'Active Staff',
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $anotherAdmin = User::factory()->create(['name' => 'Another Admin', 'role' => 'admin']);
        $inactiveStaff = User::factory()->create([
            'name' => 'Legacy Inactive Staff',
            'role' => 'staff',
            'employment_status' => 'retired',
        ]);

        $this->actingAs($admin)->get(route('admin.users', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee($inactiveStaff->name)
            ->assertSee('Deactivated')
            ->assertDontSee('Retired')
            ->assertDontSee('Terminated')
            ->assertDontSee($activeStaff->name)
            ->assertDontSee($anotherAdmin->name);

        $this->get(route('admin.users'))
            ->assertOk()
            ->assertSee($anotherAdmin->name)
            ->assertSee('Promote to department admin')
            ->assertSee('data-auto-submit-on-change', false)
            ->assertSee('admin-user-filter-toolbar')
            ->assertSee('admin-user-filter-department')
            ->assertDontSee('Apply filters')
            ->assertSee('Deactivate my account')
            ->assertSee('data-confirm-submit', false)
            ->assertSee('Change to staff');
    }

    public function test_user_directory_orders_super_admins_then_department_admins_then_staff(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'A Super Admin',
            'role' => 'admin',
        ]);
        $department = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'name' => 'B Department Admin',
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $staff = User::factory()->create([
            'name' => 'C Staff Member',
            'role' => 'staff',
            'department_id' => $department->id,
        ]);

        $this->actingAs($superAdmin)->get(route('admin.users'))
            ->assertOk()
            ->assertSeeInOrder([
                'A Super Admin',
                'B Department Admin',
                'C Staff Member',
            ]);
    }

    public function test_user_directory_uses_status_buttons_instead_of_staff_status_dropdowns(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($superAdmin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Deactivate account')
            ->assertSee('data-confirm-submit', false)
            ->assertSee('name="employment_status" value="inactive"', false)
            ->assertDontSee('Save changes')
            ->assertDontSee('name="staff['.$staff->id.'][employment_status]"', false);
    }

    public function test_last_active_super_admin_deactivation_button_is_disabled(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($superAdmin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('form="user-status-'.$superAdmin->id.'"', false)
            ->assertSee('disabled', false)
            ->assertSee('Deactivate my account')
            ->assertSee('The last active Super Admin cannot be deactivated.', false);
    }

    public function test_super_admin_deactivation_button_is_enabled_when_another_active_super_admin_remains(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);

        $this->actingAs($superAdmin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('form="user-status-'.$superAdmin->id.'"', false)
            ->assertSee('Deactivate my account')
            ->assertDontSee('disabled title="The last active Super Admin cannot be deactivated.', false);
    }

    public function test_super_admin_can_deactivate_and_reactivate_another_super_admin(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicketForStaff($target);
        $previousRememberToken = $target->remember_token;

        $this->actingAs($actor)->patch(route('admin.users.status.update', $target), [
            'employment_status' => 'inactive',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $target->name.' has been deactivated.');

        $this->assertSame('inactive', $target->fresh()->employment_status);
        $this->assertNotSame($previousRememberToken, $target->fresh()->remember_token);
        $this->assertNull($ticket->fresh()->assigned_to_user_id);
        $this->assertModelExists($target);
        $this->assertDatabaseHas('ticket_events', [
            'actor_id' => $actor->id,
            'event' => 'user_access_status_updated',
            'subject_type' => 'User',
            'subject_id' => (string) $target->id,
        ]);

        $this->patch(route('admin.users.status.update', $target), [
            'employment_status' => 'active',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $target->name.' has been reactivated.');

        $this->assertSame('active', $target->fresh()->employment_status);
    }

    public function test_last_active_super_admin_cannot_deactivate_their_own_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($superAdmin)->patch(route('admin.users.status.update', $superAdmin), [
            'employment_status' => 'inactive',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('warning', 'The last active Super Admin cannot be deactivated. Activate another Super Admin first.');

        $this->assertSame('active', $superAdmin->fresh()->employment_status);
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_deactivated_super_admin_cannot_use_an_existing_session_to_manage_accounts(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'admin',
            'employment_status' => 'inactive',
        ]);

        $this->actingAs($superAdmin)->get(route('admin.users'))
            ->assertForbidden();
    }

    public function test_super_admin_can_deactivate_their_own_account_when_another_super_admin_remains(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);

        $this->actingAs($superAdmin)->patch(route('admin.users.status.update', $superAdmin), [
            'employment_status' => 'inactive',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your Super Admin access has been deactivated.');

        $this->assertSame('inactive', $superAdmin->fresh()->employment_status);
        $this->assertGuest();
        $this->assertSame(1, User::query()
            ->where('role', 'admin')
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->count());
    }

    public function test_admin_can_promote_active_staff_and_demote_them_while_another_admin_remains(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->patch(route('admin.users.role.update', $staff), [
            'role' => 'admin',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $staff->name.' is now a Super Admin.');

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'actor_id' => $admin->id,
            'subject_type' => 'User',
            'subject_id' => (string) $staff->id,
            'event' => 'user_role_updated',
        ]);

        $staff->refresh();
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();

        $this->actingAs($admin)->patch(route('admin.users.role.update', $staff), [
            'role' => 'staff',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $staff->name.' is now a staff member.');

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'staff',
        ]);
    }

    public function test_admin_cannot_change_own_role_and_can_demote_another_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.users.role.update', $admin), [
            'role' => 'staff',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('warning', 'You cannot change your own Super Admin role.');

        $anotherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.users.role.update', $anotherAdmin), [
            'role' => 'staff',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $anotherAdmin->name.' is now a staff member.');

        $this->assertDatabaseHas('users', [
            'id' => $anotherAdmin->id,
            'role' => 'staff',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_only_active_approved_staff_can_be_promoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inactiveStaff = User::factory()->create([
            'role' => 'staff',
            'employment_status' => 'inactive',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.role.update', $inactiveStaff), [
            'role' => 'admin',
        ])->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $inactiveStaff->id,
            'role' => 'staff',
        ]);

        $pendingStaff = User::factory()->create([
            'role' => 'staff',
            'staff_approval_status' => 'pending',
        ]);

        $this->patch(route('admin.users.role.update', $pendingStaff), [
            'role' => 'admin',
        ])->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $pendingStaff->id,
            'role' => 'staff',
            'staff_approval_status' => 'pending',
        ]);
    }

    public function test_non_admins_cannot_change_user_roles(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $anotherStaff = User::factory()->create(['role' => 'staff']);

        $this->patch(route('admin.users.role.update', $anotherStaff), [
            'role' => 'admin',
        ])->assertRedirect(route('login'));

        $this->actingAs($staff)->patch(route('admin.users.role.update', $anotherStaff), [
            'role' => 'admin',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $anotherStaff->id,
            'role' => 'staff',
        ]);
    }

    public function test_role_changes_reject_roles_outside_staff_and_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->patch(route('admin.users.role.update', $staff), [
            'role' => 'requester',
        ])->assertSessionHasErrors(['role']);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'staff',
        ]);
    }

    public function test_deactivated_staff_cannot_sign_in_or_request_a_password_reset(): void
    {
        Notification::fake();
        $staff = User::factory()->create([
            'email' => 'departed@pcu.edu.ph',
            'password' => 'correct-password',
            'role' => 'staff',
            'employment_status' => 'inactive',
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $staff->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->post(route('password.email'), ['email' => $staff->email])
            ->assertSessionHas('status', 'If an account exists for that email, a reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_department_admin_cannot_assign_a_ticket_to_a_departed_employee(): void
    {
        $department = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $departedStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
            'employment_status' => 'terminated',
        ]);
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-001000',
            'department_id' => $department->id,
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Unassigned support request',
            'description' => 'The user needs help with this request.',
            'priority' => 'Low',
            'status' => 'New',
        ]);

        $this->actingAs($departmentAdmin)->put(route('staff.ticket-update', $ticket), [
            'status' => 'Assigned',
            'assigned_to_user_id' => $departedStaff->id,
        ])->assertSessionHasErrors('assigned_to_user_id');

        $this->assertNull($ticket->fresh()->assigned_to_user_id);
    }

    private function createTicketForStaff(User $staff): Ticket
    {
        return Ticket::create([
            'ticket_number' => 'ED-2026-000999',
            'assigned_to_user_id' => $staff->id,
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Assigned support request',
            'description' => 'The user needs help with this request.',
            'priority' => 'Low',
            'status' => 'New',
        ]);
    }
}
