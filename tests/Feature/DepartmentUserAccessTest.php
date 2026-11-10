<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DepartmentUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_admin_can_manage_staff_only_in_their_department(): void
    {
        $department = Department::factory()->create(['name' => 'Edtech']);
        $otherDepartment = Department::factory()->create(['name' => 'Registrar']);
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $departmentStaff = User::factory()->create([
            'name' => 'Edtech Staff',
            'department_id' => $department->id,
            'role' => 'staff',
        ]);
        $otherDepartmentStaff = User::factory()->create([
            'name' => 'Registrar Staff',
            'department_id' => $otherDepartment->id,
            'role' => 'staff',
        ]);

        $this->actingAs($departmentAdmin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee($departmentStaff->email)
            ->assertDontSee($otherDepartmentStaff->email);

        $this->post(route('admin.users.store'), [
            'staff' => [
                ['name' => 'New Edtech Staff', 'email' => 'new.edtech@pcu.edu.ph'],
            ],
        ])->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.edtech@pcu.edu.ph',
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
    }

    public function test_department_admin_cannot_manage_another_department_or_change_roles(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $otherDepartmentStaff = User::factory()->create([
            'department_id' => $otherDepartment->id,
            'role' => 'staff',
        ]);

        $this->actingAs($departmentAdmin)->put(route('admin.users.update', $otherDepartmentStaff), [
            'staff' => [
                $otherDepartmentStaff->id => [
                    'name' => 'Changed Name',
                    'email' => $otherDepartmentStaff->email,
                    'employment_status' => 'terminated',
                ],
            ],
        ])->assertNotFound();

        $this->patch(route('admin.users.role.update', $otherDepartmentStaff), [
            'role' => 'department_admin',
        ])->assertForbidden();

        $this->patch(route('admin.users.status.update', $otherDepartmentStaff), [
            'employment_status' => 'inactive',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $otherDepartmentStaff->id,
            'name' => $otherDepartmentStaff->name,
            'role' => 'staff',
            'department_id' => $otherDepartment->id,
        ]);
    }

    public function test_department_admin_can_deactivate_staff_in_their_department(): void
    {
        $department = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $departmentStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);

        $this->actingAs($departmentAdmin)->patch(route('admin.users.status.update', $departmentStaff), [
            'employment_status' => 'inactive',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', $departmentStaff->name.' has been deactivated.');

        $this->assertSame('inactive', $departmentStaff->fresh()->employment_status);
    }

    public function test_super_admin_can_assign_staff_to_a_department_and_make_them_department_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create(['name' => 'COI']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($superAdmin)->patch(route('admin.users.role.update', $staff), [
            'role' => 'department_admin',
            'department_id' => $department->id,
        ])->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
    }

    public function test_department_admin_cannot_access_global_administration(): void
    {
        $department = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);

        $this->actingAs($departmentAdmin)->get(route('admin.system-settings'))
            ->assertForbidden();
    }

    public function test_regular_staff_searches_other_department_tickets_by_id_in_read_only_mode(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $departmentTicket = $this->createTicket($department, 'TKT-OWN-DEPT', 'Department ticket');
        $otherDepartmentTicket = $this->createTicket($otherDepartment, 'TKT-OTHER-DEPT', 'Other department ticket');

        $this->actingAs($staff)->get(route('staff.tickets'))
            ->assertOk()
            ->assertSee($departmentTicket->ticket_number)
            ->assertDontSee($otherDepartmentTicket->ticket_number);

        $this->get(route('staff.tickets', ['search' => $otherDepartmentTicket->ticket_number]))
            ->assertOk()
            ->assertSee($otherDepartmentTicket->ticket_number)
            ->assertSee('View only · '.$otherDepartment->name)
            ->assertViewHas('ticketCount', 1);

        $this->get(route('staff.tickets', ['search' => 'TKT-OTHER']))
            ->assertOk()
            ->assertDontSee($otherDepartmentTicket->ticket_number);

        $this->get(route('staff.ticket-detail', $departmentTicket))
            ->assertOk()
            ->assertSee($departmentTicket->ticket_number);

        $this->get(route('staff.ticket-detail', $otherDepartmentTicket))
            ->assertOk()
            ->assertSee('Read-only view')
            ->assertSee($otherDepartment->name)
            ->assertSee($otherDepartmentTicket->description)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="body"', false)
            ->assertDontSee('Save changes')
            ->assertDontSee('Transfer to department')
            ->assertDontSee('Submit report for review');
    }

    public function test_super_admin_can_filter_ticket_queue_and_staff_by_department(): void
    {
        $department = Department::factory()->create(['name' => 'EdTech']);
        $otherDepartment = Department::factory()->create(['name' => 'Registrar']);
        $admin = User::factory()->create(['role' => 'admin']);
        $departmentStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
            'name' => 'EdTech Staff',
        ]);
        User::factory()->create([
            'role' => 'staff',
            'department_id' => $otherDepartment->id,
            'name' => 'Registrar Staff',
        ]);
        User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
            'name' => 'EdTech Admin',
        ]);
        $departmentTicket = $this->createTicket($department, 'TKT-EDTECH-QUEUE', 'EdTech request');
        $otherDepartmentTicket = $this->createTicket($otherDepartment, 'TKT-REGISTRAR-QUEUE', 'Registrar request');

        $this->actingAs($admin)
            ->get(route('staff.tickets', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee($departmentTicket->ticket_number)
            ->assertDontSee($otherDepartmentTicket->ticket_number)
            ->assertSee('Filter by department')
            ->assertSee('Filter by staff')
            ->assertSee('EdTech Staff')
            ->assertDontSee('Registrar Staff')
            ->assertDontSee('EdTech Admin')
            ->assertViewHas('ticketCount', 1)
            ->assertViewHas('selectedDepartmentId', $department->id)
            ->assertViewHas('assignees', fn ($assignees): bool => $assignees->modelKeys() === [$departmentStaff->id]);

        $this->get(route('staff.tickets'))
            ->assertOk()
            ->assertSee($departmentTicket->ticket_number)
            ->assertSee($otherDepartmentTicket->ticket_number)
            ->assertSee('disabled', false)
            ->assertSee('Select department first');
    }

    public function test_department_admin_can_view_another_department_queue_without_working_its_tickets(): void
    {
        $department = Department::factory()->create(['name' => 'EdTech']);
        $otherDepartment = Department::factory()->create(['name' => 'Registrar']);
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
            'name' => 'EdTech Staff',
        ]);
        $otherDepartmentStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $otherDepartment->id,
            'name' => 'Registrar Staff',
        ]);
        $departmentTicket = $this->createTicket($department, 'TKT-EDTECH-OWN', 'EdTech request');
        $otherDepartmentTicket = $this->createTicket($otherDepartment, 'TKT-REGISTRAR-READONLY', 'Registrar request');

        $this->actingAs($departmentAdmin)
            ->get(route('staff.tickets', ['department_id' => $otherDepartment->id]))
            ->assertOk()
            ->assertDontSee($departmentTicket->ticket_number)
            ->assertSee($otherDepartmentTicket->ticket_number)
            ->assertSee('View only · Registrar')
            ->assertSee('Registrar Staff')
            ->assertDontSee('EdTech Staff')
            ->assertViewHas('ticketCount', 1)
            ->assertViewHas('selectedDepartmentId', $otherDepartment->id)
            ->assertViewHas('assignees', fn ($assignees): bool => $assignees->modelKeys() === [$otherDepartmentStaff->id]);

        $this->get(route('staff.tickets'))
            ->assertOk()
            ->assertSee($departmentTicket->ticket_number)
            ->assertDontSee($otherDepartmentTicket->ticket_number)
            ->assertSee('EdTech Staff')
            ->assertDontSee('Registrar Staff');

        $this->put(route('staff.ticket-update', $otherDepartmentTicket), [
            'status' => 'In Progress',
        ])->assertNotFound();

        $this->assertSame('New', $otherDepartmentTicket->fresh()->status);

        $this->from(route('staff.tickets', ['department_id' => $otherDepartment->id]))
            ->get(route('staff.tickets', [
                'department_id' => $otherDepartment->id,
                'assignee' => User::query()
                    ->where('role', 'staff')
                    ->where('department_id', $department->id)
                    ->value('id'),
            ]))
            ->assertRedirect(route('staff.tickets', ['department_id' => $otherDepartment->id]))
            ->assertSessionHasErrors('assignee');
    }

    public function test_regular_staff_cannot_filter_other_departments_and_only_sees_local_staff_filters(): void
    {
        $department = Department::factory()->create(['name' => 'EdTech']);
        $otherDepartment = Department::factory()->create(['name' => 'Registrar']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
            'name' => 'EdTech Primary',
        ]);
        $departmentStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
            'name' => 'EdTech Staff',
        ]);
        User::factory()->create([
            'role' => 'staff',
            'department_id' => $otherDepartment->id,
            'name' => 'Registrar Staff',
        ]);
        $this->createTicket($department, 'TKT-STAFF-LOCAL', 'EdTech request');

        $this->actingAs($staff)
            ->get(route('staff.tickets'))
            ->assertOk()
            ->assertDontSee('Filter by department')
            ->assertSee('EdTech Staff')
            ->assertDontSee('Registrar Staff')
            ->assertViewHas('assignees', fn ($assignees): bool => $assignees->modelKeys() === [$staff->id, $departmentStaff->id]);

        $this->get(route('staff.tickets', ['department_id' => $otherDepartment->id]))
            ->assertSessionHasErrors('department_id');
    }

    public function test_regular_staff_cannot_assign_tickets(): void
    {
        $department = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $anotherStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $ticket = $this->createTicket($department, 'TKT-STAFF-ASSIGN', 'Staff assignment');

        $this->actingAs($staff)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Assigned',
                'assigned_to_user_id' => $anotherStaff->id,
            ])
            ->assertForbidden();

        $this->assertNull($ticket->fresh()->assigned_to_user_id);
    }

    public function test_department_admin_transfers_ticket_with_a_note_and_receiving_admin_assigns_it(): void
    {
        $sourceDepartment = Department::factory()->create(['name' => 'Edtech']);
        $receivingDepartment = Department::factory()->create(['name' => 'Registrar']);
        $sourceAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $sourceDepartment->id,
        ]);
        $sourceStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $sourceDepartment->id,
        ]);
        $receivingAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $receivingDepartment->id,
        ]);
        $receivingStaff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $receivingDepartment->id,
        ]);
        $ticket = $this->createTicket(
            $sourceDepartment,
            'TKT-DEPARTMENT-HANDOFF',
            'Request routed to registrar',
            $sourceStaff->id,
        );

        $this->actingAs($sourceAdmin)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSeeInOrder(['Ownership', 'Transfer ticket'])
            ->assertSee('<details class="workspace-card ticket-collapsible-card ticket-transfer-card"', false)
            ->assertSee('<summary class="workspace-card-heading ticket-collapsible-heading"', false);

        $this->actingAs($sourceAdmin)
            ->post(route('staff.ticket-transfer', $ticket), [
                'department_id' => $receivingDepartment->id,
                'handoff_note' => 'Verified the account details. Registrar must update the enrollment record.',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'department_id' => $receivingDepartment->id,
            'assigned_to_user_id' => null,
        ]);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'author_id' => $sourceAdmin->id,
            'is_internal' => true,
            'body' => "Handoff to Registrar:\n\nVerified the account details. Registrar must update the enrollment record.",
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'department_transferred',
            'description' => 'Ticket transferred from Edtech to Registrar.',
        ]);
        $transferEvent = TicketEvent::query()
            ->where('ticket_id', $ticket->id)
            ->where('event', 'department_transferred')
            ->firstOrFail();
        $this->assertSame([
            'from_department_id' => $sourceDepartment->id,
            'to_department_id' => $receivingDepartment->id,
            'previous_assignee_id' => $sourceStaff->id,
        ], $transferEvent->properties);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $receivingAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'department_transfer',
        ]);

        $this->actingAs($sourceStaff)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Read-only view')
            ->assertDontSee('name="status"', false);

        $this->actingAs($receivingAdmin)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Department handoff')
            ->assertSee($receivingStaff->displayName());

        $this->from(route('staff.ticket-detail', $ticket))
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Assigned',
                'assigned_to_user_id' => $sourceStaff->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket))
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertNull($ticket->fresh()->assigned_to_user_id);

        $this->put(route('staff.ticket-update', $ticket), [
            'status' => 'Assigned',
            'assigned_to_user_id' => $receivingStaff->id,
        ])->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertSame($receivingStaff->id, $ticket->fresh()->assigned_to_user_id);
    }

    public function test_department_admin_cannot_transfer_ticket_without_a_handoff_note(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $ticket = $this->createTicket($department, 'TKT-MISSING-HANDOFF', 'Missing handoff note');

        $this->actingAs($departmentAdmin)
            ->from(route('staff.ticket-detail', $ticket))
            ->post(route('staff.ticket-transfer', $ticket), [
                'department_id' => $otherDepartment->id,
                'handoff_note' => '',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket))
            ->assertSessionHasErrors('handoff_note');

        $this->assertSame($department->id, $ticket->fresh()->department_id);
    }

    public function test_super_admin_can_view_tickets_across_departments(): void
    {
        $firstDepartment = Department::factory()->create();
        $secondDepartment = Department::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $firstTicket = $this->createTicket($firstDepartment, 'TKT-FIRST-DEPT', 'First department');
        $secondTicket = $this->createTicket($secondDepartment, 'TKT-SECOND-DEPT', 'Second department');

        $this->actingAs($admin)->get(route('staff.tickets'))
            ->assertOk()
            ->assertSee($firstTicket->ticket_number)
            ->assertSee($secondTicket->ticket_number);
    }

    public function test_department_dashboard_and_reports_only_include_department_tickets(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $departmentTicket = $this->createTicket($department, 'TKT-QUEUE-COUNT', 'Department queue ticket');
        $this->createTicket($otherDepartment, 'TKT-OTHER-COUNT', 'Other department ticket', null, 'In Progress');

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertViewHas('openTicketCount', 1)
            ->assertViewHas('unassignedTicketCount', 1)
            ->assertViewHas('recentTickets', fn ($tickets): bool => $tickets->modelKeys() === [$departmentTicket->id]);

        $this->get(route('staff.reports', [
            'period' => 'daily',
            'reference_date' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 1);
    }

    public function test_staff_cannot_read_or_mark_read_notifications_for_another_department(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $otherDepartmentTicket = $this->createTicket($otherDepartment, 'TKT-PRIVATE-NOTICE', 'Private ticket');
        $notification = WorkspaceNotification::query()->create([
            'user_id' => $staff->id,
            'ticket_id' => $otherDepartmentTicket->id,
            'type' => 'assignment',
            'title' => 'Private ticket',
            'message' => 'This message must not be visible outside its department.',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.notifications'))
            ->assertOk()
            ->assertDontSee($notification->message);

        $this->patch(route('staff.notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_staff_can_read_cross_department_ticket_updates_and_attachments_but_cannot_change_ticket(): void
    {
        Storage::fake('private');
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
        $otherDepartmentTicket = $this->createTicket($otherDepartment, 'TKT-PRIVATE-ACTIONS', 'Private ticket');
        Storage::disk('private')->put('ticket-attachments/private.pdf', 'private attachment');
        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $otherDepartmentTicket->id,
            'original_name' => 'private.pdf',
            'disk' => 'private',
            'path' => 'ticket-attachments/private.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 18,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.ticket-updates', $otherDepartmentTicket))
            ->assertOk()
            ->assertExactJson(['latest_comment_id' => 0]);

        $this->get(route('staff.ticket-attachments.download', [$otherDepartmentTicket, $attachment]))
            ->assertOk();
        $this->get(route('staff.ticket-attachments.preview', [$otherDepartmentTicket, $attachment]))
            ->assertOk();

        $this->put(route('staff.ticket-update', $otherDepartmentTicket), [
            'status' => 'In Progress',
        ])->assertNotFound();

        $this->post(route('staff.ticket-comments.store', $otherDepartmentTicket), [
            'body' => 'Unauthorized note.',
        ])->assertNotFound();

        $this->post(route('staff.ticket-misuse-reports.store', $otherDepartmentTicket), [
            'reason' => 'spam',
        ])->assertNotFound();

        $this->post(route('staff.ticket-transfer', $otherDepartmentTicket), [
            'department_id' => $department->id,
            'handoff_note' => 'Unauthorized transfer.',
        ])->assertNotFound();

        $this->assertDatabaseCount('ticket_comments', 0);
        $this->assertSame('New', $otherDepartmentTicket->fresh()->status);
        $this->assertNull($otherDepartmentTicket->fresh()->assigned_to_user_id);
    }

    private function createTicket(
        Department $department,
        string $ticketNumber,
        string $subject,
        ?int $assigneeId = null,
    ): Ticket {
        return Ticket::query()->create([
            'ticket_number' => $ticketNumber,
            'department_id' => $department->id,
            'assigned_to_user_id' => $assigneeId,
            'requester_name' => 'Requester',
            'student_id' => 'STU-001',
            'requester_email' => 'requester@pcu.edu.ph',
            'subject' => $subject,
            'description' => 'A ticket for department workflow coverage.',
            'priority' => 'Low',
            'status' => 'New',
        ]);
    }
}
