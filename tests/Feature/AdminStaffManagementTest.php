<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_create_staff_accounts_from_the_user_directory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Hire',
            'email' => 'new.hire@pcu.edu.ph',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseMissing('users', ['email' => 'new.hire@pcu.edu.ph']);
    }

    public function test_admin_can_update_staff_and_reactivate_after_departure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'password' => 'correct-password',
            'employment_status' => 'active',
        ]);
        $ticket = $this->createTicketForStaff($staff);
        $previousRememberToken = $staff->remember_token;

        $this->actingAs($admin)->put(route('admin.users.update', $staff), [
            'staff' => [
                $staff->id => [
                    'name' => 'Retired Staff',
                    'email' => 'retired.staff@pcu.edu.ph',
                    'employment_status' => 'retired',
                ],
            ],
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', 'Staff access updated. Assigned tickets were returned to the shared queue.');

        $staff->refresh();
        $this->assertSame('Retired Staff', $staff->name);
        $this->assertSame('retired.staff@pcu.edu.ph', $staff->email);
        $this->assertSame('retired', $staff->employment_status);
        $this->assertNotSame($previousRememberToken, $staff->remember_token);
        $this->assertNull($ticket->fresh()->assigned_to_user_id);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'actor_id' => $admin->id,
            'event' => 'assignment_changed',
            'description' => 'Assignment removed because Retired Staff was marked retired.',
        ]);

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)->put(route('admin.users.update', $staff), [
            'staff' => [
                $staff->id => [
                    'name' => 'Retired Staff',
                    'email' => 'retired.staff@pcu.edu.ph',
                    'employment_status' => 'active',
                ],
            ],
        ])->assertRedirect(route('admin.users'));

        $this->post(route('login.store'), [
            'email' => 'retired.staff@pcu.edu.ph',
            'password' => 'correct-password',
        ])->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($staff->refresh());
    }

    public function test_staff_cannot_add_or_manage_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $anotherStaff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post('/admin/users', [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@pcu.edu.ph',
        ])->assertMethodNotAllowed();

        $this->actingAs($staff)->put(route('admin.users.update', $anotherStaff), [
            'staff' => [
                $anotherStaff->id => [
                    'name' => 'Unauthorized Change',
                    'email' => $anotherStaff->email,
                    'employment_status' => 'terminated',
                ],
            ],
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
            ->assertSeeInOrder(['Manage staff and admin access', 'Pending staff approvals', 'No pending staff applications']);
    }

    public function test_admin_user_directory_lists_staff_and_administrators_and_filters_by_employment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $activeStaff = User::factory()->create(['name' => 'Active Staff', 'role' => 'staff']);
        $anotherAdmin = User::factory()->create(['name' => 'Another Admin', 'role' => 'admin']);
        $retiredStaff = User::factory()->create([
            'name' => 'Retired Staff',
            'role' => 'staff',
            'employment_status' => 'retired',
        ]);

        $this->actingAs($admin)->get(route('admin.users', ['status' => 'retired']))
            ->assertOk()
            ->assertSee($retiredStaff->name)
            ->assertDontSee($activeStaff->name)
            ->assertDontSee($anotherAdmin->name);

        $this->get(route('admin.users'))
            ->assertOk()
            ->assertSee($anotherAdmin->name)
            ->assertSee('Promote to administrator')
            ->assertSee('Change to staff');
    }

    public function test_admin_can_promote_active_staff_and_demote_them_while_another_admin_remains(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->patch(route('admin.users.role.update', $staff), [
            'role' => 'admin',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $staff->name.' is now an administrator.');

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
            ->assertSessionHas('warning', 'You cannot change your own administrator role.');

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
        $retiredStaff = User::factory()->create([
            'role' => 'staff',
            'employment_status' => 'retired',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.role.update', $retiredStaff), [
            'role' => 'admin',
        ])->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $retiredStaff->id,
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
            'employment_status' => 'terminated',
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

    public function test_staff_cannot_assign_a_ticket_to_a_departed_employee(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $departedStaff = User::factory()->create([
            'role' => 'staff',
            'employment_status' => 'terminated',
        ]);
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-001000',
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Unassigned support request',
            'description' => 'The user needs help with this request.',
            'priority' => 'Low',
            'status' => 'New',
        ]);

        $this->actingAs($staff)->put(route('staff.ticket-update', $ticket), [
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
