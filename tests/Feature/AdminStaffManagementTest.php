<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_staff_and_send_a_password_setup_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Hire',
            'email' => 'new.hire@pcu.edu.ph',
        ]);

        $response->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', 'Staff account created. A password setup link was sent to new.hire@pcu.edu.ph.');
        $staff = User::query()->where('email', 'new.hire@pcu.edu.ph')->firstOrFail();
        $this->assertModelExists($staff);
        $this->assertSame('staff', $staff->role);
        $this->assertSame('active', $staff->employment_status);
        Notification::assertSentTo($staff, ResetPassword::class);
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

        $this->actingAs($staff)->post(route('admin.users.store'), [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@pcu.edu.ph',
        ])->assertForbidden();

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

    public function test_account_creation_rejects_duplicate_emails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'existing@pcu.edu.ph', 'role' => 'staff']);

        $this->actingAs($admin)->from(route('admin.users'))->post(route('admin.users.store'), [
            'name' => 'Duplicate Account',
            'email' => 'existing@pcu.edu.ph',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'existing@pcu.edu.ph')->count());
    }

    public function test_admin_user_directory_lists_real_staff_and_filters_by_employment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $activeStaff = User::factory()->create(['name' => 'Active Staff', 'role' => 'staff']);
        $retiredStaff = User::factory()->create([
            'name' => 'Retired Staff',
            'role' => 'staff',
            'employment_status' => 'retired',
        ]);

        $this->actingAs($admin)->get(route('admin.users', ['status' => 'retired']))
            ->assertOk()
            ->assertSee($retiredStaff->name)
            ->assertDontSee($activeStaff->name)
            ->assertSee('Add a staff member')
            ->assertSee('Terminated');
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
            'priority' => 'Low',
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
