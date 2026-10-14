<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_and_recent_tickets_come_from_database(): void
    {
        $department = Department::create(['name' => 'IT Services', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'admin']);
        $this->createTicket($department, 'ED-2026-000201', 'New', null);
        $this->createTicket($department, 'ED-2026-000202', 'In Progress', $staff->id);
        $this->createTicket($department, 'ED-2026-000203', 'Resolved', $staff->id);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk()
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
        $this->get(route('staff.dashboard'))->assertRedirect(route('staff.login'));
    }

    private function createTicket(Department $department, string $ticketNumber, string $status, ?int $assignedToUserId): Ticket
    {
        return Ticket::create([
            'ticket_number' => $ticketNumber,
            'assigned_to_user_id' => $assignedToUserId,
            'department_id' => $department->id,
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
