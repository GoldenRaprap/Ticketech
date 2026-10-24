<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_and_recent_tickets_come_from_database(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $this->createTicket('ED-2026-000201', 'New', null);
        $this->createTicket('ED-2026-000202', 'In Progress', $staff->id);
        $this->createTicket('ED-2026-000203', 'Resolved', $staff->id);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk()
            ->assertSee('class="staff-shell sidebar-collapsed"', false)
            ->assertSee('Philippine_Christian_University_logo.png')
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('aria-controls="staff-sidebar"', false)
            ->assertSee('id="staff-sidebar"', false)
            ->assertSee('href="'.route('staff.profile').'"', false)
            ->assertSee('title="Profile"', false)
            ->assertSee('title="Dashboard"', false)
            ->assertSee('data-sidebar-icon="ticket"', false)
            ->assertSee('data-sidebar-icon="knowledge-base"', false)
            ->assertSee('data-sidebar-icon="reports"', false)
            ->assertSee('class="sidebar-footer"', false)
            ->assertViewHas('openTicketCount', 2)
            ->assertViewHas('newTicketCount', 1)
            ->assertViewHas('unassignedTicketCount', 1)
            ->assertViewHas('assignedToMeCount', 1)
            ->assertSee('ED-2026-000201')
            ->assertSee('ED-2026-000202')
            ->assertSee('ED-2026-000203');
    }

    public function test_guest_is_redirected_away_from_staff_dashboard(): void
    {
        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    }

    public function test_staff_workspace_pages_render_their_main_content(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('ED-2026-000250', 'New', null);
        $pages = [
            ['staff.dashboard', 'Dashboard'],
            ['staff.tickets', 'Ticket queue'],
            ['staff.knowledge-base', 'Knowledge base'],
            ['staff.reports', 'Reports'],
            ['staff.notifications', 'Notifications'],
        ];

        foreach ($pages as [$routeName, $expectedContent]) {
            $this->actingAs($staff)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee($expectedContent);
        }

        $this->actingAs($staff)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Problem description')
            ->assertSeeInOrder(['Report client', 'Time tracking'])
            ->assertSee('<details class="workspace-card ticket-collapsible-card client-report-card"', false)
            ->assertSee('<details class="workspace-card ticket-collapsible-card ticket-timing-card"', false);
    }

    public function test_staff_profile_displays_the_authenticated_account_details(): void
    {
        $staff = User::factory()->create([
            'name' => 'Taylor Staff',
            'email' => 'taylor.staff@pcu.edu.ph',
            'role' => 'staff',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.profile'))
            ->assertOk()
            ->assertSee('Taylor Staff')
            ->assertSee('taylor.staff@pcu.edu.ph')
            ->assertSee('Helpdesk Staff')
            ->assertSee('Account information')
            ->assertSee(route('password.request'));
    }

    private function createTicket(string $ticketNumber, string $status, ?int $assignedToUserId): Ticket
    {
        return Ticket::create([
            'ticket_number' => $ticketNumber,
            'assigned_to_user_id' => $assignedToUserId,
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Support request '.$ticketNumber,
            'description' => 'A test request.',
            'priority' => 'Medium',
            'status' => $status,
        ]);
    }
}
