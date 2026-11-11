<?php

namespace Tests\Feature;

use App\Mail\ClientConductWarning;
use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketEvent;
use App\Models\TicketMisuseReport;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffWorkspaceFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_queue_search_filters_database_tickets(): void
    {
        $staff = $this->createStaffUser();
        $matchingTicket = $this->createTicket('ED-2026-000301', 'Network is offline');
        $this->createTicket('ED-2026-000302', 'Cannot reset my password');

        $this->actingAs($staff)
            ->get(route('staff.tickets', ['search' => 'ED-2026-000301']))
            ->assertOk()
            ->assertSee($matchingTicket->ticket_number)
            ->assertSee('data-ticket-row', false)
            ->assertSee('data-ticket-url="'.route('staff.ticket-detail', $matchingTicket).'"', false)
            ->assertDontSee('ED-2026-000302');
    }

    public function test_ticket_queue_filters_by_status_priority_and_assignee(): void
    {
        $staff = $this->createStaffUser();
        $assignee = $this->createStaffUser([
            'name' => 'Jordan Lee',
            'nickname' => 'Jo',
        ]);
        $matchingTicket = $this->createTicket('ED-2026-000311', 'Matching ticket', $assignee->id, 'High', 'In Progress');
        $this->createTicket('ED-2026-000312', 'Wrong status', $assignee->id, 'High', 'New');
        $this->createTicket('ED-2026-000313', 'Unassigned ticket', null, 'High', 'In Progress');

        $this->actingAs($staff)
            ->get(route('staff.tickets', [
                'status' => 'In Progress',
                'priority' => 'High',
                'assignee' => (string) $assignee->id,
            ]))
            ->assertOk()
            ->assertSee('data-auto-submit-on-change', false)
            ->assertDontSee('Apply filters')
            ->assertSee($matchingTicket->ticket_number)
            ->assertDontSee('ED-2026-000312')
            ->assertDontSee('ED-2026-000313')
            ->assertSee('>Jo</option>', false)
            ->assertDontSee('>Jordan Lee</option>', false);
    }

    public function test_department_admin_can_update_ticket_status_priority_and_assignee(): void
    {
        $staff = $this->createDepartmentAdmin();
        $assignee = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000303', 'Portal access issue');

        $this->actingAs($staff)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Resolved',
                'priority' => 'Urgent',
                'assigned_to_user_id' => $assignee->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $ticket->refresh();
        $this->assertSame('Resolved', $ticket->status);
        $this->assertSame('Urgent', $ticket->priority);
        $this->assertSame($assignee->id, $ticket->assigned_to_user_id);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'status_changed',
            'description' => 'Status changed from New to Resolved.',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'priority_changed',
            'description' => 'Priority changed to Urgent.',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $assignee->id,
            'ticket_id' => $ticket->id,
            'type' => 'assignment',
        ]);
    }

    public function test_staff_can_change_priority_while_a_ticket_is_unassigned(): void
    {
        $staff = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000322', 'Unassigned ticket', null, 'High');

        $this->actingAs($staff)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('name="priority"', false)
            ->assertDontSee('Assign this ticket to enable priority changes')
            ->assertSee('name="assigned_to_user_id"', false)
            ->assertSee('Take this ticket');

        $this->put(route('staff.ticket-update', $ticket), [
            'status' => 'New',
            'priority' => 'Urgent',
        ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertSame('Urgent', $ticket->fresh()->priority);
        $this->assertSame('New', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'priority_changed',
            'description' => 'Priority changed to Urgent.',
        ]);
    }

    public function test_staff_cannot_set_an_invalid_priority_for_an_unassigned_ticket(): void
    {
        $staff = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000323', 'Invalid priority ticket', null, 'High');

        $this->actingAs($staff)
            ->from(route('staff.ticket-detail', $ticket))
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'New',
                'priority' => 'Critical',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket))
            ->assertSessionHasErrors('priority');

        $this->assertSame('High', $ticket->fresh()->priority);
        $this->assertSame('New', $ticket->fresh()->status);
    }

    public function test_ticket_assignment_options_only_include_approved_active_staff(): void
    {
        $departmentAdmin = $this->createDepartmentAdmin();
        $staff = $this->createStaffUser();
        $approvedStaff = $this->createStaffUser([
            'name' => 'Taylor Morgan',
            'nickname' => 'Tay',
            'staff_approval_status' => 'approved',
        ]);
        $pendingStaff = $this->createStaffUser([
            'staff_approval_status' => 'pending',
        ]);
        $deniedStaff = $this->createStaffUser([
            'staff_approval_status' => 'denied',
        ]);
        $ticket = $this->createTicket('ED-2026-000319', 'Assignment options ticket');

        $expectedAssigneeIds = [$departmentAdmin->id, $staff->id, $approvedStaff->id];
        sort($expectedAssigneeIds);

        $this->actingAs($departmentAdmin)
            ->get(route('staff.tickets'))
            ->assertOk()
            ->assertViewHas('assignees', function ($assignees) use ($expectedAssigneeIds): bool {
                $actualIds = $assignees->modelKeys();
                sort($actualIds);

                return $actualIds === $expectedAssigneeIds;
            })
            ->assertSee('>Tay</option>', false)
            ->assertDontSee('>Taylor Morgan</option>', false);

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('name="priority"', false)
            ->assertViewHas('staffUsers', function ($staffUsers) use ($departmentAdmin, $staff, $approvedStaff): bool {
                $expectedIds = [$departmentAdmin->id, $staff->id, $approvedStaff->id];
                $actualIds = $staffUsers->modelKeys();
                sort($expectedIds);
                sort($actualIds);

                return $actualIds === $expectedIds;
            })
            ->assertSee('>Tay</option>', false)
            ->assertDontSee('>Taylor Morgan</option>', false)
            ->assertDontSee($pendingStaff->name)
            ->assertDontSee($deniedStaff->name);
    }

    public function test_staff_cannot_assign_a_ticket_to_a_denied_staff_account(): void
    {
        $departmentAdmin = $this->createDepartmentAdmin();
        $deniedStaff = $this->createStaffUser([
            'staff_approval_status' => 'denied',
        ]);
        $ticket = $this->createTicket('ED-2026-000320', 'Denied assignee ticket');

        $this->actingAs($departmentAdmin)
            ->from(route('staff.ticket-detail', $ticket))
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'In Progress',
                'assigned_to_user_id' => $deniedStaff->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket))
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertNull($ticket->fresh()->assigned_to_user_id);
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $ticket->id]);
    }

    public function test_ticket_update_preserves_assignee_when_field_is_omitted(): void
    {
        $staff = $this->createStaffUser();
        $assignee = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000314', 'Portal access issue', $assignee->id);

        $this->actingAs($staff)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'In Progress',
                'priority' => 'High',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertSame($assignee->id, $ticket->fresh()->assigned_to_user_id);
    }

    public function test_ticket_detail_renders_after_updating_assignment_and_resolution(): void
    {
        $staff = $this->createDepartmentAdmin();
        $assignee = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000316', 'Resolved request');

        $this->actingAs($staff)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Resolved',
                'assigned_to_user_id' => $assignee->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Ticket number')
            ->assertSee('<strong>ED-2026-000316</strong>', false)
            ->assertSee('Assigned')
            ->assertSee('Resolved');

        $ticket = $ticket->fresh();
        $this->assertInstanceOf(Carbon::class, $ticket->assigned_at);
        $this->assertInstanceOf(Carbon::class, $ticket->resolved_at);
    }

    public function test_staff_ticket_detail_detects_new_replies_and_restricts_update_checks_to_staff(): void
    {
        $staff = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000317', 'New requester reply');
        $existingComment = $ticket->comments()->create([
            'author_name' => 'Jamie Student',
            'body' => 'Initial requester reply.',
            'is_internal' => false,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('data-ticket-comment-check', false)
            ->assertSee('data-last-comment-id="'.$existingComment->id.'"', false)
            ->assertSee('A new reply has been added.')
            ->assertSee('Refresh ticket');

        $this->actingAs($staff)
            ->get(route('staff.ticket-updates', $ticket))
            ->assertOk()
            ->assertJsonPath('latest_comment_id', $existingComment->id);

        $newComment = $ticket->comments()->create([
            'author_name' => 'Jamie Student',
            'body' => 'The issue happened again.',
            'is_internal' => false,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.ticket-updates', $ticket))
            ->assertOk()
            ->assertJsonPath('latest_comment_id', $newComment->id);

        $requester = User::factory()->create(['role' => 'requester']);
        $this->actingAs($requester)
            ->get(route('staff.ticket-updates', $ticket))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('staff.ticket-updates', $ticket))
            ->assertRedirect(route('login'));
    }

    public function test_staff_can_report_client_conduct_for_admin_review(): void
    {
        $staff = $this->createStaffUser();
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket('ED-2026-000318', 'Repeated spam requests');

        $this->actingAs($staff)
            ->post(route('staff.ticket-misuse-reports.store', $ticket), [
                'reason' => 'spam',
                'details' => 'The requester submitted the same unrelated message repeatedly.',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertDatabaseHas('ticket_misuse_reports', [
            'ticket_id' => $ticket->id,
            'reporter_id' => $staff->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $admin->id,
            'ticket_id' => $ticket->id,
            'type' => 'client_report',
        ]);
        $adminNotification = WorkspaceNotification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'client_report')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.moderation'))
            ->assertOk()
            ->assertSee('Repeated spam requests')
            ->assertSee('Issue warning')
            ->assertSee('Block email');

        $this->actingAs($staff)
            ->get(route('admin.moderation'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('staff.notifications.read', $adminNotification))
            ->assertRedirect(route('admin.moderation'));
    }

    public function test_admin_can_warn_a_reported_client_and_warning_is_shown_to_them(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket('ED-2026-000319', 'Client warning test');
        $report = $ticket->misuseReports()->create([
            'reporter_id' => User::factory()->create(['role' => 'staff'])->id,
            'reason' => 'irrelevant',
            'details' => 'The request was deliberately unrelated to helpdesk support.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.moderation.review', $report), ['action' => 'warn'])
            ->assertRedirect(route('admin.moderation'));

        $this->assertDatabaseHas('client_moderations', [
            'email' => 'jamie@example.edu',
            'status' => 'warning',
            'acted_by_id' => $admin->id,
            'cleared_at' => null,
        ]);
        $this->assertDatabaseHas('ticket_misuse_reports', [
            'id' => $report->id,
            'status' => 'reviewed',
            'action' => 'warn',
            'reviewed_by_id' => $admin->id,
        ]);
        Mail::assertSent(ClientConductWarning::class, fn (ClientConductWarning $mail): bool => $mail->hasTo('jamie@example.edu'));

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'JAMIE@example.edu',
        ])->assertOk()
            ->assertSee('PCU Helpdesk conduct warning')
            ->assertSee('only for genuine support requests');
    }

    public function test_admin_can_block_a_reported_client_email_from_new_submissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $ticket = $this->createTicket('ED-2026-000321', 'Client block test');
        $report = $ticket->misuseReports()->create([
            'reporter_id' => User::factory()->create(['role' => 'staff'])->id,
            'reason' => 'repeated',
            'details' => 'The client continued to submit duplicate tickets after a warning.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.moderation.review', $report), ['action' => 'block'])
            ->assertRedirect(route('admin.moderation'));

        $this->assertDatabaseHas('client_moderations', [
            'email' => 'jamie@example.edu',
            'status' => 'blocked',
            'source_report_id' => $report->id,
            'acted_by_id' => $admin->id,
        ]);

        $this->from(route('ticket.create'))
            ->post(route('ticket.store'), [
                'name' => 'Jamie Student',
                'student_id' => '2023000001',
                'email' => 'JAMIE@example.edu',
                'department_id' => $department->id,
                'subject' => 'Blocked submission',
                'message' => 'This must be rejected.',
            ])
            ->assertRedirect(route('ticket.create'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_staff_can_record_first_assignment_and_resolution_durations_in_ticket_detail(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $departmentAdmin = $this->createDepartmentAdmin();
        $staff = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000320', 'Ticket timing test');

        $this->travelTo('2026-10-21 10:45:00');
        $this->actingAs($departmentAdmin)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Assigned',
                'assigned_to_user_id' => $staff->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->travelTo('2026-10-21 12:30:00');
        $ticket->refresh();
        $this->actingAs($departmentAdmin)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Resolved',
                'priority' => 'Low',
                'assigned_to_user_id' => $staff->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $ticket->refresh();
        $this->assertNotNull($ticket->first_assigned_at);
        $this->assertNotNull($ticket->last_resolved_at);

        $this->travelTo('2026-10-22 12:30:00');
        $this->actingAs($departmentAdmin)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Waiting for User',
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => 'The issue came up again.',
        ])->assertRedirect(route('ticket.check'));

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Not resolved yet')
            ->assertSee('2h 30m');

        $this->travelTo('2026-10-22 14:30:00');
        $ticket->refresh();
        $this->actingAs($departmentAdmin)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Resolved',
                'priority' => 'Low',
                'assigned_to_user_id' => $staff->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('45m')
            ->assertSee('2h 0m')
            ->assertSee('1d 4h 30m');
    }

    public function test_staff_cannot_assign_a_ticket_to_a_requester(): void
    {
        $departmentAdmin = $this->createDepartmentAdmin();
        $requester = User::factory()->create(['role' => 'requester']);
        $ticket = $this->createTicket('ED-2026-000304', 'Portal access issue');

        $this->actingAs($departmentAdmin)
            ->from(route('staff.ticket-detail', $ticket))
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'In Progress',
                'assigned_to_user_id' => $requester->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket))
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertSame('New', $ticket->fresh()->status);
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseHas('ticket_events', [
            'actor_id' => $departmentAdmin->id,
            'event' => 'staff_action_performed',
            'route_name' => 'staff.ticket-update',
        ]);
    }

    public function test_staff_internal_notes_notify_ticket_access_staff_without_exposing_note_content(): void
    {
        Storage::fake('private');
        Notification::fake();
        $staff = $this->createStaffUser();
        $assignee = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000305', 'Portal access issue', $assignee->id);

        $this->actingAs($staff)
            ->post(route('staff.ticket-comments.store', $ticket), [
                'body' => 'Escalated to the platform team.',
                'is_internal' => '1',
                'attachment' => [UploadedFile::fake()->create('internal-evidence.png', 20, 'image/png')],
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'author_id' => $staff->id,
            'body' => 'Escalated to the platform team.',
            'is_internal' => true,
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'internal_note_added',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $assignee->id,
            'ticket_id' => $ticket->id,
            'type' => 'internal_note',
            'message' => $ticket->ticket_number.' · '.$ticket->subject.' received a staff note.',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'user_id' => $assignee->id,
            'ticket_id' => $ticket->id,
            'message' => 'Escalated to the platform team.',
        ]);
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'comment_id' => $ticket->comments()->firstOrFail()->id,
            'original_name' => 'internal-evidence.png',
        ]);

        $attachment = TicketAttachment::query()->firstOrFail();
        $this->get(route('staff.ticket-attachments.preview', [$ticket, $attachment]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->post(route('ticket.lookup'), [
            'ticket' => $ticket->ticket_number,
            'email' => $ticket->requester_email,
        ])->assertOk();

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertDontSee('internal-evidence.png');

        $this->get(route('ticket.attachments.preview', $attachment))->assertNotFound();
    }

    public function test_staff_ticket_activity_separates_public_replies_and_internal_notes(): void
    {
        $staff = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000317', 'Conversation display');
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'We are checking your access now.',
            'is_internal' => false,
        ]);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'Check the authentication logs.',
            'is_internal' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('We are checking your access now.')
            ->assertSee('Check the authentication logs.')
            ->assertSee('conversation-entry--internal')
            ->assertSee('Public reply')
            ->assertSee('Internal note')
            ->assertSee('Internal notes are staff-only')
            ->assertSee('Use the checkbox to keep a note visible only to staff.');
    }

    public function test_public_staff_reply_notifies_the_assigned_staff_member(): void
    {
        Storage::fake('private');
        $staff = $this->createStaffUser();
        $assignee = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000315', 'Portal access issue', $assignee->id);

        $this->actingAs($staff)
            ->post(route('staff.ticket-comments.store', $ticket), [
                'body' => 'We have received your request and are investigating.',
                'attachment' => [UploadedFile::fake()->create('progress.png', 20, 'image/png')],
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'body' => 'We have received your request and are investigating.',
            'is_internal' => false,
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $assignee->id,
            'ticket_id' => $ticket->id,
            'type' => 'reply',
        ]);
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'original_name' => 'progress.png',
        ]);

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('progress.png')
            ->assertSee('data-image-preview', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="attachment[]"', false);
    }

    public function test_staff_attachment_download_is_scoped_to_its_ticket(): void
    {
        Storage::fake('private');
        $staff = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000306', 'Portal access issue');
        $otherTicket = $this->createTicket('ED-2026-000307', 'Other issue');
        Storage::disk('private')->put('ticket-attachments/support.pdf', 'private attachment');
        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'original_name' => 'support.pdf',
            'disk' => 'private',
            'path' => 'ticket-attachments/support.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 18,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.ticket-attachments.download', [$ticket, $attachment]))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('staff.ticket-attachments.download', [$otherTicket, $attachment]))
            ->assertNotFound();
    }

    public function test_admin_can_create_public_knowledge_article_and_draft_is_hidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $articleData = [
            'title' => 'Connect to campus Wi-Fi',
            'category' => 'Network',
            'summary' => 'Set up a device to use campus Wi-Fi.',
            'body' => "Open your device's wireless settings.\nChoose the campus network.",
            'is_published' => '1',
        ];

        $this->actingAs($admin)
            ->get(route('admin.knowledge-base'))
            ->assertOk()
            ->assertSee('data-sidebar-icon="knowledge-base"', false)
            ->assertSee('Knowledge Base');

        $this->actingAs($admin)
            ->get(route('admin.knowledge-base.create'))
            ->assertOk()
            ->assertSee('data-article-content-editor', false)
            ->assertSee('aria-pressed="false" aria-label="Bold"', false)
            ->assertSee('aria-label="Italic"', false)
            ->assertSee('aria-label="Heading 1"', false)
            ->assertSee('aria-label="Heading 2"', false)
            ->assertSee('aria-label="Bulleted list"', false);

        $this->actingAs($admin)
            ->post(route('admin.knowledge-base.store'), $articleData)
            ->assertSessionHasNoErrors();

        $article = KnowledgeArticle::query()->firstOrFail();
        $this->assertSame($admin->id, $article->author_id);
        $this->assertTrue($article->is_published);
        $publicReader = User::factory()->create(['role' => 'requester']);

        $this->actingAs($admin)->get(route('admin.knowledge-base'))
            ->assertOk()
            ->assertDontSee('article-public-link', false);
        $this->actingAs($publicReader)->get(route('knowledge-base'))
            ->assertOk()
            ->assertSee('class="page-wrap help-scene public-knowledge-scene"', false)
            ->assertSee('Helpful Articles')
            ->assertSee($article->title);
        $this->actingAs($publicReader)->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee('class="page-wrap help-scene public-article-scene"', false)
            ->assertDontSee('class="crumb-wrap"', false)
            ->assertDontSee('class="breadcrumb"', false)
            ->assertDontSee('class="ticket-breadcrumb"', false)
            ->assertSee('Choose the campus network.');

        $this->actingAs($admin)
            ->put(route('admin.knowledge-base.update', $article), [
                ...$articleData,
                'title' => 'Updated campus Wi-Fi guide',
                'body' => 'Use your campus account to connect.',
            ])
            ->assertRedirect(route('admin.knowledge-base'));

        $article->refresh();
        $this->assertSame('updated-campus-wi-fi-guide', $article->slug);
        $this->assertSame('<p>Use your campus account to connect.</p>', $article->body);
        $this->actingAs($publicReader)->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee('Use your campus account to connect.');

        $draft = KnowledgeArticle::create([
            'title' => 'Internal setup procedure',
            'slug' => 'internal-setup-procedure',
            'category' => 'Internal',
            'summary' => 'Staff-only setup details.',
            'body' => 'Do not display this draft publicly.',
            'author_id' => $admin->id,
            'is_published' => false,
        ]);

        $this->actingAs($publicReader)->get(route('knowledge-base'))
            ->assertDontSee($draft->title);
        $this->actingAs($publicReader)->get(route('knowledge-base.show', $draft->slug))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.knowledge-base.destroy', $article))
            ->assertRedirect(route('admin.knowledge-base'));
        $this->assertModelMissing($article);
    }

    public function test_formatted_article_content_is_rendered_and_unsafe_markup_is_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $body = '<h1>Getting started</h1><p><b>Bold</b> <strong>Important</strong> <em>details</em></p><ul><li>First step</li></ul><a href="javascript:alert(1)" onclick="alert(1)">Unsafe link</a><script>alert(1)</script>';

        $this->actingAs($admin)
            ->post(route('admin.knowledge-base.store'), [
                'title' => 'Formatted guide',
                'category' => 'Accounts',
                'summary' => 'A guide with formatted content.',
                'body' => $body,
                'is_published' => '1',
            ])
            ->assertSessionHasNoErrors();

        $article = KnowledgeArticle::query()->firstOrFail();
        $this->assertStringContainsString('<h1>Getting started</h1>', $article->body);
        $this->assertStringContainsString('<b>Bold</b>', $article->body);
        $this->assertStringContainsString('<strong>Important</strong>', $article->body);
        $this->assertStringContainsString('<em>details</em>', $article->body);
        $this->assertStringContainsString('<ul><li>First step</li></ul>', $article->body);
        $this->assertStringNotContainsString('javascript:', $article->body);
        $this->assertStringNotContainsString('onclick', $article->body);
        $this->assertStringNotContainsString('<script', $article->body);

        $publicReader = User::factory()->create(['role' => 'requester']);

        $this->actingAs($publicReader)->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee('<h1>Getting started</h1>', false)
            ->assertSee('<b>Bold</b>', false)
            ->assertSee('<strong>Important</strong>', false)
            ->assertSee('Unsafe link')
            ->assertDontSee('javascript:alert(1)')
            ->assertDontSee('alert(1)');
    }

    public function test_admin_can_upload_and_remove_article_image_attachments(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $firstImageUpload = $this->fakeKnowledgeBaseImage('wifi-diagram.png');
        $secondImageUpload = $this->fakeKnowledgeBaseImage('wifi-screenshot.png');
        $articleData = [
            'title' => 'Campus Wi-Fi visual guide',
            'category' => 'Network',
            'summary' => 'Connect a device to campus Wi-Fi.',
            'body' => 'Follow the steps in the attached image.',
            'is_published' => '1',
            'images' => [$firstImageUpload],
        ];

        $this->actingAs($admin)
            ->post(route('admin.knowledge-base.store'), $articleData)
            ->assertSessionHasNoErrors();

        $article = KnowledgeArticle::query()->firstOrFail();
        $firstImage = $article->images()->firstOrFail();
        $publicReader = User::factory()->create(['role' => 'requester']);
        Storage::disk('public')->assertExists($firstImage->path);
        $this->actingAs($publicReader)->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee($firstImage->url(), false)
            ->assertSee('Article images');
        $this->get($firstImage->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($admin)
            ->put(route('admin.knowledge-base.update', $article), [
                ...$articleData,
                'images' => [$secondImageUpload],
                'remove_images' => [$firstImage->id],
            ])
            ->assertRedirect(route('admin.knowledge-base'));

        $secondImage = $article->images()->firstOrFail();
        $this->assertDatabaseMissing('knowledge_article_images', ['id' => $firstImage->id]);
        Storage::disk('public')->assertMissing($firstImage->path);
        Storage::disk('public')->assertExists($secondImage->path);

        $this->actingAs($admin)
            ->delete(route('admin.knowledge-base.destroy', $article))
            ->assertRedirect(route('admin.knowledge-base'));
        Storage::disk('public')->assertMissing($secondImage->path);
        $this->assertDatabaseMissing('knowledge_article_images', ['id' => $secondImage->id]);
    }

    public function test_admin_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.knowledge-base.create'))
            ->post(route('admin.knowledge-base.store'), [
                'title' => 'Network setup',
                'category' => 'Network',
                'summary' => 'Connect to the network.',
                'body' => 'Open the Wi-Fi settings.',
                'images' => [UploadedFile::fake()->createWithContent('unsafe.svg', '<svg><script>alert(1)</script></svg>')],
            ])
            ->assertRedirect(route('admin.knowledge-base.create'))
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('knowledge_articles', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_anonymous_article_ratings_remain_hidden_until_five_are_submitted(): void
    {
        $article = $this->createKnowledgeArticle(User::factory()->create(['role' => 'admin']));

        $this->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee('Select a star rating')
            ->assertSee('Submit rating')
            ->assertDontSee('Individual ratings will appear')
            ->assertDontSee('of 5 received')
            ->assertDontSee('data-public-review-list', false)
            ->assertDontSee('Anonymous review');

        foreach ([5, 4, 4, 4, 4, 4, 3, 3, 3, 3] as $index => $rating) {
            if ($index === 5) {
                $this->travel(61)->seconds();
            }

            $this->post(route('knowledge-base.reviews.store', $article->slug), ['rating' => $rating])
                ->assertRedirect(route('knowledge-base.show', $article->slug));
        }

        $this->assertDatabaseCount('knowledge_article_reviews', 10);
        $response = $this->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee('3.7')
            ->assertSee('style="width: 74.0%"', false)
            ->assertDontSee('data-public-review-list', false)
            ->assertDontSee('Anonymous review')
            ->assertDontSee('of 5 received');
    }

    public function test_excessive_article_ratings_return_to_the_article_with_a_wait_message(): void
    {
        $article = $this->createKnowledgeArticle(User::factory()->create(['role' => 'admin']));
        $reviewUrl = route('knowledge-base.reviews.store', $article->slug);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($reviewUrl, ['rating' => 5])
                ->assertRedirect(route('knowledge-base.show', $article->slug));
        }

        $this->post($reviewUrl, ['rating' => 5])
            ->assertRedirect(route('knowledge-base.show', $article->slug))
            ->assertSessionHas('review_rate_limited');

        $this->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee("You're rating articles a little too quickly.", false)
            ->assertSee('Please wait about 1 minute before submitting another rating.');

        $this->assertDatabaseCount('knowledge_article_reviews', 5);
    }

    public function test_average_article_ratings_are_shown_on_home_and_knowledge_base_after_threshold(): void
    {
        $article = $this->createKnowledgeArticle(User::factory()->create(['role' => 'admin']));
        foreach ([5, 4, 4, 4] as $rating) {
            $article->reviews()->create(['rating' => $rating]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('public-article-rating', false);
        $this->get(route('knowledge-base'))
            ->assertOk()
            ->assertDontSee('public-article-rating', false);

        $article->reviews()->create(['rating' => 4]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Average rating 4.2 out of 5')
            ->assertSee('style="width: 84.0%"', false)
            ->assertSee('public-article-card-footer', false)
            ->assertDontSee('data-review-rating', false);
        $this->get(route('knowledge-base'))
            ->assertOk()
            ->assertSee('Average rating 4.2 out of 5')
            ->assertSee('style="width: 84.0%"', false)
            ->assertSee('public-article-card-footer', false)
            ->assertDontSee('data-review-rating', false);
    }

    public function test_admin_can_change_the_review_visibility_threshold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createKnowledgeArticle($admin);
        $publicReader = User::factory()->create(['role' => 'requester']);

        $this->actingAs($admin)
            ->get(route('admin.knowledge-base'))
            ->assertOk()
            ->assertSee('Minimum ratings')
            ->assertSee('value="5"', false);

        $this->actingAs($admin)
            ->put(route('admin.knowledge-base.review-settings'), ['review_threshold' => 2])
            ->assertRedirect(route('admin.knowledge-base'));
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'knowledge_base.review_visibility_threshold',
            'value' => '2',
        ]);

        $this->actingAs($publicReader)->post(route('knowledge-base.reviews.store', $article->slug), ['rating' => 5]);
        $this->actingAs($publicReader)->post(route('knowledge-base.reviews.store', $article->slug), ['rating' => 1]);
        $this->actingAs($publicReader)->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee('style="width: 60.0%"', false)
            ->assertSee('3.0');

        $this->actingAs($admin)
            ->put(route('admin.knowledge-base.review-settings'), ['review_threshold' => 0])
            ->assertSessionHasErrors('review_threshold');

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)
            ->put(route('admin.knowledge-base.review-settings'), ['review_threshold' => 1])
            ->assertForbidden();
    }

    public function test_anonymous_ratings_reject_invalid_scores_and_unpublished_articles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $draft = $this->createKnowledgeArticle($admin, [
            'slug' => 'draft-knowledge-guide',
            'is_published' => false,
            'published_at' => null,
        ]);
        $published = $this->createKnowledgeArticle($admin);

        $this->post(route('knowledge-base.reviews.store', $draft->slug), ['rating' => 5])
            ->assertNotFound();
        $this->from(route('knowledge-base.show', $published->slug))
            ->post(route('knowledge-base.reviews.store', $published->slug), ['rating' => 6])
            ->assertRedirect(route('knowledge-base.show', $published->slug))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('knowledge_article_reviews', 0);
    }

    public function test_reports_are_calculated_from_ticket_records(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00'));
        $staff = $this->createStaffUser();
        $this->createTicket('ED-2026-000308', 'New network issue', null, 'Urgent');
        $this->createTicket('ED-2026-000309', 'Resolved account issue', null, 'Low', 'Resolved');

        $this->actingAs($staff)
            ->get(route('staff.reports', [
                'period' => 'daily',
                'reference_date' => '2026-11-10',
            ]))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 2)
            ->assertViewHas('openTicketCount', 1)
            ->assertViewHas('resolvedTicketCount', 1)
            ->assertSee('Tickets by priority')
            ->assertSee('Incoming vs. resolved')
            ->assertSee('data-report-period-form', false)
            ->assertSee('Export Excel (.xlsx)')
            ->assertDontSee('Individual assignee performance export')
            ->assertDontSee('Apply period')
            ->assertSee('Urgent');
    }

    public function test_report_resolution_trend_includes_tickets_resolved_before_reopening(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $staff = $this->createStaffUser();
        $reopenedTicket = $this->createTicket('ED-2026-000373', 'Previously resolved ticket', null, 'Low', 'In Progress');
        $reopenedTicket->forceFill([
            'created_at' => Carbon::parse('2026-03-15 08:00:00'),
            'resolved_at' => null,
            'last_resolved_at' => Carbon::parse('2026-03-15 09:00:00'),
            'last_reopened_at' => Carbon::parse('2026-03-15 10:00:00'),
        ])->save();

        $this->actingAs($staff)
            ->get(route('staff.reports', [
                'period' => 'daily',
                'reference_date' => '2026-03-15',
            ]))
            ->assertOk()
            ->assertViewHas('openTicketCount', 1)
            ->assertViewHas('resolvedTicketCount', 0)
            ->assertViewHas('trendBuckets', fn (Collection $buckets): bool => $buckets->sum('resolved') === 1);
    }

    public function test_super_admin_can_filter_report_summary_and_export_by_department(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $selectedDepartment = Department::factory()->create(['name' => 'Network Services']);
        $otherDepartment = Department::factory()->create(['name' => 'Registrar']);
        $selectedTicket = $this->createTicket('ED-2026-000309', 'Network department ticket');
        $selectedTicket->forceFill(['department_id' => $selectedDepartment->id])->save();
        $otherTicket = $this->createTicket('ED-2026-000310', 'Registrar department ticket');
        $otherTicket->forceFill(['department_id' => $otherDepartment->id])->save();
        $filters = [
            'department_id' => $selectedDepartment->id,
            'period' => 'daily',
            'reference_date' => '2026-03-15',
        ];

        $this->actingAs($admin)
            ->get(route('staff.reports', $filters))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 1)
            ->assertViewHas('selectedDepartmentId', $selectedDepartment->id)
            ->assertViewHas('selectedDepartmentName', 'Network Services')
            ->assertSee('Department')
            ->assertSee('All departments')
            ->assertSee('Registrar');

        $ticketSheet = $this->readXlsxEntry(
            $this->get(route('staff.reports.export', $filters))->assertOk()->streamedContent(),
            'xl/worksheets/sheet2.xml',
        );

        $this->assertStringContainsString($selectedTicket->ticket_number, $ticketSheet);
        $this->assertStringNotContainsString($otherTicket->ticket_number, $ticketSheet);
    }

    public function test_weekly_reports_include_the_selected_dates_monday_through_sunday(): void
    {
        $this->travelTo(Carbon::parse('2026-03-04 12:00:00'));
        $staff = $this->createStaffUser();
        $mondayTicket = $this->createTicket('ED-2026-000324', 'Monday ticket');
        $sundayTicket = $this->createTicket('ED-2026-000325', 'Sunday ticket');
        $outsideTicket = $this->createTicket('ED-2026-000326', 'Next week ticket');
        $mondayTicket->forceFill(['created_at' => Carbon::parse('2026-03-02 00:00:00 UTC')])->save();
        $sundayTicket->forceFill(['created_at' => Carbon::parse('2026-03-08 10:00:00 UTC')])->save();
        $outsideTicket->forceFill(['created_at' => Carbon::parse('2026-03-09 00:00:00 UTC')])->save();

        $this->actingAs($staff)
            ->get(route('staff.reports', [
                'period' => 'weekly',
                'reference_date' => '2026-03-04',
            ]))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 2)
            ->assertSee('Mar 2, 2026 – Mar 8, 2026');
    }

    public function test_monthly_reports_and_exports_include_the_selected_calendar_month(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $staff = $this->createStaffUser();
        $previousMonthTicket = $this->createTicket('ED-2026-000337', 'Previous month ticket');
        $firstMonthTicket = $this->createTicket('ED-2026-000338', 'First day of month');
        $lastMonthTicket = $this->createTicket('ED-2026-000339', 'Last day of month');
        $nextMonthTicket = $this->createTicket('ED-2026-000340', 'Next month ticket');
        $previousMonthTicket->forceFill(['created_at' => Carbon::parse('2026-02-28 15:59:00 UTC')])->save();
        $firstMonthTicket->forceFill(['created_at' => Carbon::parse('2026-02-28 16:00:00 UTC')])->save();
        $lastMonthTicket->forceFill(['created_at' => Carbon::parse('2026-03-31 15:59:59 UTC')])->save();
        $nextMonthTicket->forceFill(['created_at' => Carbon::parse('2026-03-31 16:00:00 UTC')])->save();
        $filters = [
            'period' => 'monthly',
            'reference_date' => '2026-03-15',
        ];

        $this->actingAs($staff)
            ->get(route('staff.reports', $filters))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 2)
            ->assertSee('Monthly')
            ->assertSee('Mar 1, 2026 – Mar 31, 2026');

        $workbook = $this->get(route('staff.reports.export', $filters))
            ->assertOk()
            ->streamedContent();
        $workbookXml = $this->readXlsxEntry($workbook, 'xl/workbook.xml');
        $summarySheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet1.xml');
        $ticketsSheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet2.xml');
        $styles = $this->readXlsxEntry($workbook, 'xl/styles.xml');

        $this->assertStringContainsString('name="Summary"', $workbookXml);
        $this->assertStringContainsString('name="Tickets"', $workbookXml);
        $this->assertStringContainsString('<mergeCell ref="A1:D1"/>', $summarySheet);
        $this->assertStringContainsString('TICKETS BY PRIORITY', $summarySheet);
        $this->assertStringContainsString('■', $summarySheet);
        $this->assertStringContainsString('FFDCEAF7', $styles);
        $this->assertStringContainsString('Monthly', $summarySheet);
        $this->assertStringContainsString('2026-03-01', $summarySheet);
        $this->assertStringContainsString('2026-03-31', $summarySheet);
        $this->assertStringContainsString($firstMonthTicket->ticket_number, $ticketsSheet);
        $this->assertStringContainsString($lastMonthTicket->ticket_number, $ticketsSheet);
        $this->assertStringNotContainsString($previousMonthTicket->ticket_number, $ticketsSheet);
        $this->assertStringNotContainsString($nextMonthTicket->ticket_number, $ticketsSheet);
    }

    public function test_report_export_volume_bars_match_each_category_share_of_all_tickets(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $staff = $this->createStaffUser();

        $this->createTicket('ED-2026-000351', 'High ticket one', null, 'High');
        $this->createTicket('ED-2026-000352', 'High ticket two', null, 'High');
        $this->createTicket('ED-2026-000353', 'High ticket three', null, 'High');
        $this->createTicket('ED-2026-000354', 'Medium ticket', null, 'Medium', 'In Progress');
        $this->createTicket('ED-2026-000355', 'Resolved low ticket', null, 'Low', 'Resolved');
        $this->createTicket('ED-2026-000356', 'Closed low ticket', null, 'Low', 'Closed');

        $workbook = $this->actingAs($staff)
            ->get(route('staff.reports.export', [
                'period' => 'monthly',
                'reference_date' => '2026-03-15',
            ]))
            ->assertOk()
            ->streamedContent();
        $summarySheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet1.xml');
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($summarySheet));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $this->assertSame(
            'Volume (each block = 5%)',
            $xpath->query('//x:c[@r="D12"]/x:is/x:t')->item(0)?->textContent,
        );

        foreach ([
            'D13' => '■■■■■■■■■■□□□□□□□□□□',
            'D14' => '■■■■■■■□□□□□□□□□□□□□',
            'D15' => '■■■□□□□□□□□□□□□□□□□□',
            'D19' => '■■■□□□□□□□□□□□□□□□□□',
            'D20' => '■■■□□□□□□□□□□□□□□□□□',
            'D21' => '■■■■■■■■■■□□□□□□□□□□',
            'D22' => '■■■□□□□□□□□□□□□□□□□□',
        ] as $cellReference => $expectedBar) {
            $this->assertSame(
                $expectedBar,
                $xpath->query('//x:c[@r="'.$cellReference.'"]/x:is/x:t')->item(0)?->textContent,
            );
        }
    }

    public function test_ticket_statuses_have_consistent_colors_in_both_report_exports(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = $this->createStaffUser();
        $statuses = [
            'New' => 'FFDDEBF7',
            'Assigned' => 'FFDDEFF2',
            'In Progress' => 'FFFFF2CC',
            'Waiting for User' => 'FFFCE4D6',
            'On Hold' => 'FFE2E8F0',
            'Resolved' => 'FFE2F0D9',
            'Closed' => 'FFE7E6E6',
        ];

        foreach (array_keys($statuses) as $index => $status) {
            $ticket = $this->createTicket(
                'ED-2026-'.str_pad((string) (400 + $index), 6, '0', STR_PAD_LEFT),
                $status.' status style ticket',
                $staff->id,
            );
            $ticket->forceFill(['status' => $status])->save();
        }

        $filters = [
            'period' => 'daily',
            'reference_date' => '2026-03-15',
        ];
        $standardWorkbook = $this->actingAs($admin)
            ->get(route('staff.reports.export', $filters))
            ->assertOk()
            ->streamedContent();
        $staffPerformanceWorkbook = $this->get(route('admin.reports.staff-performance.export', [
            ...$filters,
            'staff_ids' => [$staff->id],
        ]))
            ->assertOk()
            ->streamedContent();

        $assertStatusColors = function (string $workbook) use ($statuses): void {
            $worksheet = new \DOMDocument;
            $styles = new \DOMDocument;
            $this->assertTrue($worksheet->loadXML($this->readXlsxEntry($workbook, 'xl/worksheets/sheet2.xml')));
            $this->assertTrue($styles->loadXML($this->readXlsxEntry($workbook, 'xl/styles.xml')));
            $worksheetXPath = new \DOMXPath($worksheet);
            $stylesXPath = new \DOMXPath($styles);
            $worksheetXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $stylesXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cellFormats = $stylesXPath->query('//x:cellXfs/x:xf');
            $fills = $stylesXPath->query('//x:fills/x:fill');

            foreach (array_values($statuses) as $index => $expectedColor) {
                $statusCell = $worksheetXPath->query('//x:c[@r="F'.($index + 2).'"]')->item(0);
                $this->assertNotNull($statusCell, 'Expected a status cell for each ticket row.');
                $style = $cellFormats->item((int) $statusCell->getAttribute('s'));
                $this->assertNotNull($style);
                $fill = $fills->item((int) $style->getAttribute('fillId'));
                $this->assertNotNull($fill);
                $color = $stylesXPath->query('x:patternFill/x:fgColor', $fill)->item(0);
                $this->assertNotNull($color);
                $this->assertSame($expectedColor, $color->getAttribute('rgb'));
            }
        };

        $assertStatusColors($standardWorkbook);
        $assertStatusColors($staffPerformanceWorkbook);
    }

    public function test_admin_can_export_selected_staff_progress_and_resolution_for_a_report_period(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $firstStaff = User::factory()->create([
            'name' => 'Avery Staff',
            'email' => 'avery@example.edu',
            'role' => 'staff',
        ]);
        $secondStaff = User::factory()->create([
            'name' => 'Blair Staff',
            'email' => 'blair@example.edu',
            'role' => 'staff',
        ]);
        $inactiveStaff = User::factory()->create([
            'name' => 'Inactive Staff',
            'role' => 'staff',
            'employment_status' => 'inactive',
        ]);
        $pendingStaff = User::factory()->create([
            'name' => 'Pending Staff',
            'role' => 'staff',
            'staff_approval_status' => 'pending',
        ]);
        $unselectedStaff = User::factory()->create(['role' => 'staff']);

        $this->createTicket('ED-2026-000361', 'Avery new ticket', $firstStaff->id, 'Low', 'New');
        $this->createTicket('ED-2026-000362', 'Avery progress ticket', $firstStaff->id, 'Medium', 'In Progress');
        $firstResolvedTicket = $this->createTicket('ED-2026-000363', 'Avery resolved ticket', $firstStaff->id, 'High', 'Resolved');
        $firstClosedTicket = $this->createTicket('ED-2026-000364', 'Avery closed ticket', $firstStaff->id, 'High', 'Closed');
        $secondResolvedTicket = $this->createTicket('ED-2026-000365', 'Blair resolved ticket', $secondStaff->id, 'Medium', 'Resolved');
        $this->createTicket('ED-2026-000366', 'Blair new ticket', $secondStaff->id, 'Low', 'New');
        $unselectedTicket = $this->createTicket('ED-2026-000367', 'Unselected staff ticket', $unselectedStaff->id, 'Low', 'Resolved');
        $firstResolvedTicket->forceFill([
            'created_at' => Carbon::parse('2026-03-01 08:00:00'),
            'resolved_at' => Carbon::parse('2026-03-01 09:00:00'),
        ])->save();
        $firstClosedTicket->forceFill([
            'created_at' => Carbon::parse('2026-03-01 08:00:00'),
            'resolved_at' => Carbon::parse('2026-03-01 11:00:00'),
        ])->save();
        $secondResolvedTicket->forceFill([
            'created_at' => Carbon::parse('2026-03-02 08:00:00'),
            'resolved_at' => Carbon::parse('2026-03-02 12:00:00'),
        ])->save();

        $filters = [
            'period' => 'monthly',
            'reference_date' => '2026-03-15',
        ];
        $this->actingAs($admin)
            ->get(route('staff.reports', $filters))
            ->assertOk()
            ->assertSee('Individual assignee performance export')
            ->assertSee('name="staff_ids[]"', false)
            ->assertSee($firstStaff->name)
            ->assertSee($secondStaff->name)
            ->assertDontSee($inactiveStaff->name)
            ->assertDontSee($pendingStaff->name);

        $response = $this->get(route('admin.reports.staff-performance.export', [
            ...$filters,
            'staff_ids' => [$firstStaff->id, $secondStaff->id],
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $workbook = $response->streamedContent();
        $workbookXml = $this->readXlsxEntry($workbook, 'xl/workbook.xml');
        $summarySheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet1.xml');
        $ticketSheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet2.xml');
        $summaryXml = new \DOMDocument;
        $ticketXml = new \DOMDocument;
        $summaryDocument = new \DOMDocument;
        $this->assertTrue($summaryXml->loadXML($workbookXml));
        $this->assertTrue($ticketXml->loadXML($ticketSheet));
        $this->assertTrue($summaryDocument->loadXML($summarySheet));
        $xpath = new \DOMXPath($summaryDocument);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $ticketDocument = new \DOMDocument;
        $this->assertTrue($ticketDocument->loadXML($ticketSheet));
        $ticketXPath = new \DOMXPath($ticketDocument);
        $ticketXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $this->assertStringContainsString('name="Assignee Summary"', $workbookXml);
        $this->assertStringContainsString('name="Ticket Details"', $workbookXml);
        $this->assertStringContainsString('autoFilter ref="A4:L6"', $summarySheet);
        $this->assertStringContainsString('autoFilter ref="A1:J7"', $ticketSheet);
        $this->assertSame('0.5', $xpath->query('//x:c[@r="J5"]/x:v')->item(0)?->textContent);
        $this->assertSame('2', $xpath->query('//x:c[@r="K5"]/x:v')->item(0)?->textContent);
        $this->assertSame('0.5', $xpath->query('//x:c[@r="J6"]/x:v')->item(0)?->textContent);
        $this->assertSame('4', $xpath->query('//x:c[@r="K6"]/x:v')->item(0)?->textContent);
        $this->assertStringContainsString('ED-2026-000363', $ticketSheet);
        $this->assertStringContainsString('ED-2026-000365', $ticketSheet);
        $this->assertStringNotContainsString($unselectedTicket->ticket_number, $ticketSheet);
        $this->assertSame('1', $ticketXPath->query('//x:c[@r="I4"]/x:v')->item(0)?->textContent);
        $this->assertSame('3', $ticketXPath->query('//x:c[@r="I5"]/x:v')->item(0)?->textContent);
        $this->assertSame('4', $ticketXPath->query('//x:c[@r="I6"]/x:v')->item(0)?->textContent);
    }

    public function test_reports_include_department_admin_and_super_admin_assignees_and_current_statuses(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $department = $this->defaultDepartment();
        $departmentAdmin = User::factory()->create([
            'name' => 'Alex Assignee',
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $superAdmin = User::factory()->create([
            'name' => 'Alex Assignee',
            'role' => 'admin',
        ]);
        $this->createTicket('ED-2026-000370', 'Waiting ticket', $departmentAdmin->id, 'Low', 'Waiting for User');
        $this->createTicket('ED-2026-000371', 'On hold ticket', $departmentAdmin->id, 'Low', 'On Hold');
        $this->createTicket('ED-2026-000372', 'Super Admin ticket', $superAdmin->id);
        $filters = [
            'department_id' => $department->id,
            'period' => 'daily',
            'reference_date' => '2026-03-15',
        ];

        $this->actingAs($admin)
            ->get(route('staff.reports', $filters))
            ->assertOk()
            ->assertSee('Alex Assignee · Department Admin')
            ->assertSee('Alex Assignee · Super Admin')
            ->assertViewHas('assigneeWorkload', function (Collection $workload): bool {
                return $workload->contains(fn (object $row): bool => $row->assignee_name === 'Alex Assignee'
                        && $row->assignee_role_label === 'Department Admin'
                        && (int) $row->total_tickets === 2)
                    && $workload->contains(fn (object $row): bool => $row->assignee_name === 'Alex Assignee'
                        && $row->assignee_role_label === 'Super Admin'
                        && (int) $row->total_tickets === 1);
            });

        $standardTicketSheet = $this->readXlsxEntry(
            $this->get(route('staff.reports.export', $filters))->assertOk()->streamedContent(),
            'xl/worksheets/sheet2.xml',
        );
        $this->assertStringContainsString('autoFilter ref="A1:J4"', $standardTicketSheet);
        $this->assertStringContainsString('Department Admin', $standardTicketSheet);
        $this->assertStringContainsString('Super Admin', $standardTicketSheet);

        $workbook = $this->get(route('admin.reports.staff-performance.export', [
            ...$filters,
            'staff_ids' => [$departmentAdmin->id, $superAdmin->id],
        ]))->assertOk()->streamedContent();
        $summarySheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet1.xml');
        $summaryDocument = new \DOMDocument;
        $this->assertTrue($summaryDocument->loadXML($summarySheet));
        $summaryXPath = new \DOMXPath($summaryDocument);
        $summaryXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $this->assertStringContainsString('Department Admin', $summarySheet);
        $this->assertStringContainsString('Super Admin', $summarySheet);
        $this->assertSame('2', $summaryXPath->query('//x:c[@r="C5"]/x:v')->item(0)?->textContent);
        $this->assertSame('1', $summaryXPath->query('//x:c[@r="G5"]/x:v')->item(0)?->textContent);
        $this->assertSame('1', $summaryXPath->query('//x:c[@r="H5"]/x:v')->item(0)?->textContent);
        $this->assertSame('1', $summaryXPath->query('//x:c[@r="C6"]/x:v')->item(0)?->textContent);
        $this->assertSame('1', $summaryXPath->query('//x:c[@r="D6"]/x:v')->item(0)?->textContent);
    }

    public function test_admin_staff_performance_export_is_limited_to_the_selected_department(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $selectedDepartment = Department::factory()->create(['name' => 'Network Services']);
        $otherDepartment = Department::factory()->create(['name' => 'Registrar']);
        $selectedStaff = User::factory()->create([
            'name' => 'Network Staff',
            'role' => 'staff',
            'department_id' => $selectedDepartment->id,
        ]);
        User::factory()->create([
            'name' => 'Registrar Staff',
            'role' => 'staff',
            'department_id' => $otherDepartment->id,
        ]);
        $selectedTicket = $this->createTicket('ED-2026-000368', 'Network ticket', $selectedStaff->id);
        $selectedTicket->forceFill(['department_id' => $selectedDepartment->id])->save();
        $otherTicket = $this->createTicket('ED-2026-000369', 'Other department ticket', $selectedStaff->id);
        $otherTicket->forceFill(['department_id' => $otherDepartment->id])->save();
        $filters = [
            'department_id' => $selectedDepartment->id,
            'period' => 'daily',
            'reference_date' => '2026-03-15',
        ];

        $this->actingAs($admin)
            ->get(route('staff.reports', $filters))
            ->assertOk()
            ->assertSee('Network Staff')
            ->assertDontSee('Registrar Staff');

        $ticketSheet = $this->readXlsxEntry(
            $this->get(route('admin.reports.staff-performance.export', [
                ...$filters,
                'staff_ids' => [$selectedStaff->id],
            ]))->assertOk()->streamedContent(),
            'xl/worksheets/sheet2.xml',
        );

        $this->assertStringContainsString($selectedTicket->ticket_number, $ticketSheet);
        $this->assertStringNotContainsString($otherTicket->ticket_number, $ticketSheet);

        $this->from(route('staff.reports'))->get(route('admin.reports.staff-performance.export', [
            ...$filters,
            'staff_ids' => [$selectedStaff->id, User::query()->where('name', 'Registrar Staff')->value('id')],
        ]))->assertRedirect(route('staff.reports'))
            ->assertSessionHasErrors('staff_ids.1');
    }

    public function test_admin_staff_performance_export_rejects_non_staff_selections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requester = User::factory()->create(['role' => 'requester']);

        $this->actingAs($admin)
            ->from(route('staff.reports'))
            ->get(route('admin.reports.staff-performance.export', [
                'staff_ids' => [$requester->id],
            ]))
            ->assertRedirect(route('staff.reports'))
            ->assertSessionHasErrors('staff_ids.0');
    }

    public function test_admin_staff_performance_export_rejects_inactive_or_unapproved_staff(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inactiveStaff = User::factory()->create([
            'role' => 'staff',
            'employment_status' => 'inactive',
        ]);
        $pendingStaff = User::factory()->create([
            'role' => 'staff',
            'staff_approval_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('staff.reports'))
            ->get(route('admin.reports.staff-performance.export', [
                'staff_ids' => [$inactiveStaff->id, $pendingStaff->id],
            ]))
            ->assertRedirect(route('staff.reports'))
            ->assertSessionHasErrors(['staff_ids.0', 'staff_ids.1']);
    }

    public function test_yearly_reports_use_the_selected_calendar_year(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
        $staff = $this->createStaffUser();
        $priorYearTicket = $this->createTicket('ED-2026-000327', 'Prior year ticket');
        $currentYearTicket = $this->createTicket('ED-2026-000328', 'Current year ticket');
        $priorYearTicket->forceFill(['created_at' => Carbon::parse('2025-12-31 15:59:00 UTC')])->save();
        $currentYearTicket->forceFill(['created_at' => Carbon::parse('2025-12-31 16:00:00 UTC')])->save();

        $this->actingAs($staff)
            ->get(route('staff.reports', [
                'period' => 'yearly',
                'reference_date' => '2025-11-05',
            ]))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 1)
            ->assertSee('Jan 1, 2025 – Dec 31, 2025');
    }

    public function test_custom_report_trend_counts_only_tickets_inside_the_selected_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-04-04 12:00:00'));
        $staff = $this->createStaffUser();
        $beforePeriodTicket = $this->createTicket('ED-2026-000331', 'Before custom range');
        $this->createTicket('ED-2026-000332', 'First day of custom range');
        $this->createTicket('ED-2026-000333', 'Last day of custom range');
        $afterPeriodTicket = $this->createTicket('ED-2026-000334', 'After custom range');
        $beforePeriodTicket->forceFill(['created_at' => Carbon::parse('2026-04-03 15:59:00 UTC')])->save();
        $afterPeriodTicket->forceFill(['created_at' => Carbon::parse('2026-05-10 16:00:00 UTC')])->save();

        $this->actingAs($staff)
            ->get(route('staff.reports', [
                'period' => 'custom',
                'start_date' => '2026-04-04',
                'end_date' => '2026-05-10',
            ]))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 2)
            ->assertSeeText('2 incoming tickets');
    }

    public function test_long_custom_report_periods_use_a_bounded_trend_chart(): void
    {
        $this->travelTo(Carbon::parse('2026-04-04 12:00:00'));
        $staff = $this->createStaffUser();
        $firstTicket = $this->createTicket('ED-2026-000335', 'Early report ticket');
        $lastTicket = $this->createTicket('ED-2026-000336', 'Recent report ticket');
        $firstTicket->forceFill(['created_at' => Carbon::parse('2020-06-15 12:00:00')])->save();
        $lastTicket->forceFill(['created_at' => Carbon::parse('2025-11-30 12:00:00')])->save();

        $this->actingAs($staff)
            ->get(route('staff.reports', [
                'period' => 'custom',
                'start_date' => '2020-01-01',
                'end_date' => '2025-12-31',
            ]))
            ->assertOk()
            ->assertViewHas('trendBuckets', fn (Collection $buckets): bool => $buckets->count() === 12
                && $buckets->sum('received') === 2);
    }

    public function test_custom_report_period_requires_an_end_date_on_or_after_the_start_date(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.reports', [
                'period' => 'custom',
                'start_date' => '2026-05-10',
                'end_date' => '2026-05-09',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_report_exports_redirect_guests_to_staff_sign_in(): void
    {
        $this->get(route('staff.reports.export'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_can_export_custom_period_tickets_as_formula_safe_xlsx(): void
    {
        $this->travelTo(Carbon::parse('2026-04-04 12:00:00'));
        config()->set('app.display_timezone', 'Asia/Manila');
        $staff = $this->createStaffUser();
        $assignee = $this->createStaffUser(['name' => 'Taylor Morgan']);
        $includedTicket = $this->createTicket(
            'ED-2026-000329',
            '=HYPERLINK("https://example.com", "click")',
            $assignee->id,
            'Urgent',
        );
        $outsideTicket = $this->createTicket('ED-2026-000330', 'Outside the selected period');
        $outsideTicket->forceFill(['created_at' => Carbon::parse('2026-04-03 15:59:00 UTC')])->save();

        $response = $this->actingAs($staff)
            ->get(route('staff.reports.export', [
                'period' => 'custom',
                'start_date' => '2026-04-04',
                'end_date' => '2026-04-04',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'ticketech-report-custom-2026-04-04-to-2026-04-04.xlsx',
            $response->baseResponse->headers->get('content-disposition'),
        );
        $workbook = $response->streamedContent();
        $workbookXml = $this->readXlsxEntry($workbook, 'xl/workbook.xml');
        $summarySheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet1.xml');
        $ticketsSheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet2.xml');
        $styles = $this->readXlsxEntry($workbook, 'xl/styles.xml');
        $ticketsSheetText = html_entity_decode($ticketsSheet, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $this->assertStringStartsWith("PK\x03\x04", $workbook);
        $this->assertStringContainsString('name="Summary"', $workbookXml);
        $this->assertStringContainsString('name="Tickets"', $workbookXml);
        $this->assertStringContainsString('Custom', $summarySheet);
        $this->assertStringContainsString('autoFilter ref="A1:J2"', $ticketsSheet);
        $this->assertStringContainsString('topLeftCell="A2"', $ticketsSheet);
        $this->assertStringContainsString('<cols>', $ticketsSheet);
        $this->assertStringContainsString('FFFCE4E4', $styles);
        $this->assertStringContainsString('FFDDEBF7', $styles);
        $this->assertStringContainsString($includedTicket->ticket_number, $ticketsSheetText);
        $this->assertStringContainsString('2026-04-04 20:00:00', $ticketsSheetText);
        $this->assertStringContainsString("'=HYPERLINK", $ticketsSheetText);
        $this->assertStringContainsString($assignee->name, $ticketsSheetText);
        $this->assertStringNotContainsString($outsideTicket->ticket_number, $ticketsSheetText);
    }

    public function test_admin_audit_logs_show_real_ticket_events_and_filter_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'name' => 'Taylor Morgan',
            'nickname' => 'Tay',
            'role' => 'staff',
        ]);
        $ticket = $this->createTicket('ED-2026-000322', 'Audit log target');
        $otherTicket = $this->createTicket('ED-2026-000323', 'Filtered audit event');
        $event = $ticket->events()->create([
            'actor_id' => $staff->id,
            'event' => 'status_changed',
            'description' => 'Status changed from New to In Progress.',
        ]);
        $otherTicket->events()->create([
            'actor_id' => $admin->id,
            'event' => 'priority_changed',
            'description' => 'Priority changed to Urgent.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs', [
                'search' => 'In Progress',
                'actor' => $staff->id,
            ]))
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee($ticket->ticket_number)
            ->assertSee('Status changed from New to In Progress.')
            ->assertSee($staff->name)
            ->assertDontSee('Apply filters')
            ->assertSee('admin-console-button--export', false)
            ->assertSee('<option value="'.$staff->id.'" selected>Tay</option>', false)
            ->assertSee(route('staff.ticket-detail', $ticket), false)
            ->assertSee(str_replace('&', '&amp;', route('admin.audit-logs.export', [
                'search' => 'In Progress',
                'actor' => $staff->id,
            ])), false)
            ->assertDontSee($otherTicket->ticket_number)
            ->assertDontSee('Priority changed to Urgent.')
            ->assertViewHas('events', fn ($events): bool => $events->total() === 1 && $events->first()->id === $event->id);
    }

    public function test_admin_can_export_all_matching_audit_events_to_excel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = User::factory()->create(['name' => 'Taylor Morgan', 'role' => 'staff']);
        $otherActor = User::factory()->create(['name' => 'Jordan Lee', 'role' => 'staff']);
        $ticket = $this->createTicket('ED-2026-000381', 'Audit export target');
        $matchingEvent = TicketEvent::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'event' => 'sensitive_change',
            'description' => '=HYPERLINK("https://example.com", "click") Export marker',
            'request_method' => 'PATCH',
            'route_name' => 'admin.users.update',
            'subject_type' => 'User',
            'subject_id' => '42',
            'subject_name' => 'Export target',
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Test browser',
            'properties' => ['before' => ['role' => 'staff'], 'after' => ['role' => 'admin']],
        ]);
        $matchingEvent->forceFill([
            'created_at' => '2026-11-04 10:00:00',
            'updated_at' => '2026-11-04 10:00:00',
        ])->save();
        TicketEvent::create([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'event' => 'other_change',
            'description' => 'Different audit event',
            'created_at' => '2026-11-04 10:01:00',
            'updated_at' => '2026-11-04 10:01:00',
        ]);
        TicketEvent::create([
            'actor_id' => $otherActor->id,
            'actor_name' => $otherActor->name,
            'event' => 'sensitive_change',
            'description' => 'Export marker for another user',
            'created_at' => '2026-11-04 10:02:00',
            'updated_at' => '2026-11-04 10:02:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.audit-logs.export', [
                'search' => 'Export marker',
                'date' => '2026-11-04',
                'actor' => $actor->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString(
            'ticketech-audit-logs-',
            $response->baseResponse->headers->get('content-disposition'),
        );
        $workbook = $response->streamedContent();
        $workbookXml = $this->readXlsxEntry($workbook, 'xl/workbook.xml');
        $sheet = $this->readXlsxEntry($workbook, 'xl/worksheets/sheet1.xml');
        $styles = $this->readXlsxEntry($workbook, 'xl/styles.xml');
        $sheetText = html_entity_decode(
            $sheet,
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
        );

        $this->assertStringStartsWith("PK\x03\x04", $workbook);
        $this->assertStringContainsString('name="Audit Logs"', $workbookXml);
        $this->assertStringContainsString('autoFilter ref="A1:J2"', $sheet);
        $this->assertMatchesRegularExpression('~<col[^>]*min="10"[^>]*width="72"[^>]*>~', $sheet);
        $this->assertStringContainsString('Date and time', $sheetText);
        $this->assertStringContainsString('2026-11-04 18:00:00', $sheetText);
        $this->assertStringContainsString('Audit Details', $sheetText);
        $this->assertStringContainsString('rgb="FFD9EAF7"', $styles);
        $this->assertStringContainsString('rgb="FF1F4E79"', $styles);
        $this->assertStringContainsString($ticket->ticket_number, $sheetText);
        $this->assertStringContainsString("'=HYPERLINK", $sheetText);
        $this->assertStringContainsString('Before: {"role":"staff"}', $sheetText);
        $this->assertStringNotContainsString('Different audit event', $sheetText);
        $this->assertStringNotContainsString('another user', $sheetText);
    }

    public function test_staff_can_read_only_their_own_notifications(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $anotherStaff = User::factory()->create(['role' => 'staff']);
        $notification = WorkspaceNotification::create([
            'user_id' => $staff->id,
            'type' => 'new_ticket',
            'title' => 'New support ticket',
            'message' => 'ED-2026-000310 · Portal issue',
        ]);
        $otherNotification = WorkspaceNotification::create([
            'user_id' => $anotherStaff->id,
            'type' => 'new_ticket',
            'title' => 'Private update',
            'message' => 'Do not expose this to other staff.',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.notifications'))
            ->assertOk()
            ->assertSee($notification->message)
            ->assertDontSee($otherNotification->message);

        $this->actingAs($staff)
            ->patch(route('staff.notifications.read', $otherNotification))
            ->assertNotFound();

        $this->actingAs($staff)
            ->patch(route('staff.notifications.read', $notification))
            ->assertRedirect(route('staff.notifications'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_sidebar_notification_badge_shows_unread_count_and_caps_at_nine_plus(): void
    {
        Notification::fake();
        $staff = $this->createStaffUser();

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('sidebar-notification-badge');

        foreach (range(1, 9) as $index) {
            WorkspaceNotification::create([
                'user_id' => $staff->id,
                'type' => 'new_ticket',
                'title' => 'New support ticket '.$index,
                'message' => 'A ticket needs review.',
            ]);
        }

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee(
                '<span class="sidebar-notification-badge sidebar-notification-badge--blue" aria-label="9 unread notifications">9</span>',
                false,
            );

        WorkspaceNotification::create([
            'user_id' => $staff->id,
            'type' => 'new_ticket',
            'title' => 'New support ticket 10',
            'message' => 'Another ticket needs review.',
        ]);

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee(
                '<span class="sidebar-notification-badge sidebar-notification-badge--blue" aria-label="10 unread notifications">9+</span>',
                false,
            );
    }

    public function test_admin_sidebar_shows_blue_pending_user_and_report_badges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(10)->create([
            'role' => 'staff',
            'staff_approval_status' => 'pending',
        ]);
        User::factory()->create(['role' => 'staff']);

        foreach ([
            ['ED-2026-001001', 'Pending report one', 'pending'],
            ['ED-2026-001002', 'Pending report two', 'pending'],
            ['ED-2026-001003', 'Reviewed report', 'reviewed'],
        ] as [$ticketNumber, $subject, $status]) {
            $ticket = $this->createTicket($ticketNumber, $subject);
            TicketMisuseReport::create([
                'ticket_id' => $ticket->id,
                'reporter_id' => $admin->id,
                'reason' => 'spam',
                'status' => $status,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(
                '<span class="sidebar-notification-badge sidebar-notification-badge--blue" aria-label="10 pending staff applications">9+</span>',
                false,
            )
            ->assertSee(
                '<span class="sidebar-notification-badge sidebar-notification-badge--blue" aria-label="2 pending reports">2</span>',
                false,
            );
    }

    public function test_department_admin_user_access_badge_counts_only_pending_users_in_their_department(): void
    {
        $departmentAdmin = $this->createDepartmentAdmin();
        $otherDepartment = Department::factory()->create();
        User::factory()->count(2)->create([
            'role' => 'staff',
            'department_id' => $departmentAdmin->department_id,
            'staff_approval_status' => 'pending',
        ]);
        User::factory()->create([
            'role' => 'staff',
            'department_id' => $otherDepartment->id,
            'staff_approval_status' => 'pending',
        ]);
        User::factory()->create([
            'role' => 'staff',
            'department_id' => null,
            'staff_approval_status' => 'pending',
        ]);

        $this->actingAs($departmentAdmin)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee(
                '<span class="sidebar-notification-badge sidebar-notification-badge--blue" aria-label="3 pending staff applications">3</span>',
                false,
            )
            ->assertDontSee('pending reports');
    }

    public function test_ticket_notifications_render_as_clickable_rows_without_an_open_button(): void
    {
        $staff = $this->createStaffUser();
        $ticket = $this->createTicket('ED-2026-000399', 'Notification ticket');
        $notification = WorkspaceNotification::create([
            'user_id' => $staff->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
            'title' => 'New support ticket',
            'message' => 'A ticket is waiting for review.',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.notifications'))
            ->assertOk()
            ->assertDontSee('type="submit">Open', false)
            ->assertSee('data-notification-row role="button" tabindex="0"', false)
            ->assertSee('data-notification-action', false)
            ->assertSee('action="'.route('staff.notifications.read', $notification).'"', false)
            ->assertSee('name="_method" value="patch"', false);

        $this->actingAs($staff)
            ->patch(route('staff.notifications.read', $notification))
            ->assertRedirect(route('staff.ticket-detail', $ticket));
    }

    public function test_staff_can_update_profile_without_changing_their_email(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'taylor.original@pcu.edu.ph',
        ]);
        $emailVerifiedAt = $staff->email_verified_at;

        $this->actingAs($staff)
            ->put(route('staff.profile.update'), [
                'name' => 'Taylor Updated',
                'email' => 'taylor.updated@pcu.edu.ph',
            ])
            ->assertRedirect(route('staff.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Taylor Updated',
            'email' => 'taylor.original@pcu.edu.ph',
        ]);

        $this->assertSame(
            $emailVerifiedAt?->getTimestamp(),
            $staff->fresh()->email_verified_at?->getTimestamp(),
        );

        $this->actingAs($staff->fresh())
            ->get(route('staff.profile'))
            ->assertOk()
            ->assertSee('id="profile-email" type="email"', false)
            ->assertSee('value="taylor.original@pcu.edu.ph"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="email"', false)
            ->assertSee('Contact an administrator if it needs to be changed.');
    }

    public function test_admin_cannot_change_their_email_from_the_profile(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.original@pcu.edu.ph',
        ]);
        $emailVerifiedAt = $admin->email_verified_at;

        $this->actingAs($admin)
            ->put(route('staff.profile.update'), [
                'name' => $admin->name,
                'email' => 'admin.changed@pcu.edu.ph',
            ])
            ->assertRedirect(route('staff.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'admin.original@pcu.edu.ph',
        ]);
        $this->assertSame(
            $emailVerifiedAt?->getTimestamp(),
            $admin->fresh()->email_verified_at?->getTimestamp(),
        );
    }

    public function test_staff_can_save_a_nickname_while_preserving_their_full_name(): void
    {
        $staff = User::factory()->create([
            'name' => 'Taylor Morgan',
            'role' => 'staff',
        ]);

        $this->actingAs($staff)
            ->put(route('staff.profile.update'), [
                'name' => 'Taylor Morgan',
                'nickname' => 'Tay',
                'email' => $staff->email,
            ])
            ->assertRedirect(route('staff.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Taylor Morgan',
            'nickname' => 'Tay',
        ]);

        $this->actingAs($staff->fresh())
            ->get(route('staff.profile'))
            ->assertOk()
            ->assertSee('<h2>Tay</h2>', false)
            ->assertSee('Full name: Taylor Morgan')
            ->assertSee('value="Tay"', false);

        $this->actingAs($staff->fresh())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('>Tay</strong>', false);
    }

    public function test_nickname_must_not_exceed_80_characters(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->from(route('staff.profile'))
            ->put(route('staff.profile.update'), [
                'name' => $staff->name,
                'nickname' => str_repeat('a', 81),
                'email' => $staff->email,
            ])
            ->assertRedirect(route('staff.profile'))
            ->assertSessionHasErrors('nickname');

        $this->assertNull($staff->fresh()->nickname);
    }

    public function test_staff_without_a_nickname_uses_their_full_name_for_display(): void
    {
        $staff = User::factory()->create([
            'name' => 'Taylor Morgan',
            'nickname' => null,
            'role' => 'staff',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.profile'))
            ->assertOk()
            ->assertSee('<h2>Taylor Morgan</h2>', false)
            ->assertDontSee('Full name: Taylor Morgan');
    }

    public function test_staff_can_upload_and_replace_their_profile_photo(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create(['role' => 'staff']);
        $oldPhotoPath = Storage::disk('public')->putFile(
            'profile-photos',
            $this->fakeProfilePhoto('old-photo.png'),
        );
        $staff->forceFill(['profile_photo_path' => $oldPhotoPath])->save();

        $this->actingAs($staff)
            ->put(route('staff.profile.update'), [
                'name' => $staff->name,
                'email' => $staff->email,
                'avatar' => $this->fakeProfilePhoto('new-photo.png'),
            ])
            ->assertRedirect(route('staff.profile'));

        $newPhotoPath = $staff->fresh()->profile_photo_path;
        $photoUrl = route('public-images.show', ['path' => $newPhotoPath]);
        $this->assertNotSame($oldPhotoPath, $newPhotoPath);
        Storage::disk('public')->assertExists($newPhotoPath);
        Storage::disk('public')->assertMissing($oldPhotoPath);

        $this->get($photoUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($staff->fresh())
            ->get(route('staff.profile'))
            ->assertOk()
            ->assertSee('class="profile-photo-image" src="'.$photoUrl.'"', false)
            ->assertSee($photoUrl, false);

        $this->actingAs($staff->fresh())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('class="profile-photo-image" src="'.$photoUrl.'"', false)
            ->assertSee($photoUrl, false);
    }

    public function test_public_image_endpoint_does_not_expose_files_outside_image_directories(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('private-uploads/secret.png', 'not a public image');

        $this->get(route('public-images.show', ['path' => 'private-uploads/secret.png']))
            ->assertNotFound();
    }

    public function test_staff_profile_rejects_non_image_uploads_without_replacing_the_current_photo(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create(['role' => 'staff']);
        $existingPhotoPath = Storage::disk('public')->putFile(
            'profile-photos',
            $this->fakeProfilePhoto('current-photo.png'),
        );
        $staff->forceFill(['profile_photo_path' => $existingPhotoPath])->save();

        $this->actingAs($staff)
            ->from(route('staff.profile'))
            ->put(route('staff.profile.update'), [
                'name' => $staff->name,
                'email' => $staff->email,
                'avatar' => UploadedFile::fake()->create('not-an-image.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect(route('staff.profile'))
            ->assertSessionHasErrors('avatar');

        $this->assertSame($existingPhotoPath, $staff->fresh()->profile_photo_path);
        Storage::disk('public')->assertExists($existingPhotoPath);
        $this->assertSame(1, count(Storage::disk('public')->allFiles('profile-photos')));
    }

    private function createTicket(
        string $ticketNumber,
        string $subject,
        ?int $assigneeId = null,
        string $priority = 'Low',
        string $status = 'New',
    ): Ticket {
        return Ticket::create([
            'ticket_number' => $ticketNumber,
            'department_id' => $this->defaultDepartment()->id,
            'assigned_to_user_id' => $assigneeId,
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'subject' => $subject,
            'description' => 'The user needs help with this request.',
            'priority' => $priority,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStaffUser(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'staff',
            'department_id' => $this->defaultDepartment()->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDepartmentAdmin(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $this->defaultDepartment()->id,
            ...$attributes,
        ]);
    }

    private function defaultDepartment(): Department
    {
        return Department::query()->firstOrCreate(['name' => 'Staff workspace test']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createKnowledgeArticle(User $author, array $attributes = []): KnowledgeArticle
    {
        return KnowledgeArticle::create([
            'author_id' => $author->id,
            'title' => 'Campus Wi-Fi connection guide',
            'slug' => 'campus-wi-fi-connection-guide',
            'category' => 'Network',
            'summary' => 'Learn how to connect to the campus wireless network.',
            'body' => 'Open the wireless network settings on your device.',
            'is_published' => true,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    private function fakeKnowledgeBaseImage(string $filename): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $filename,
            file_get_contents(public_path('images/TickeTech_Logo_Transparent.png')),
        );
    }

    private function fakeProfilePhoto(string $filename): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $filename,
            file_get_contents(public_path('images/TickeTech_Logo_Transparent.png')),
        );
    }

    private function readXlsxEntry(string $workbook, string $entry): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ticketech-test-');

        if ($path === false) {
            $this->fail('Unable to create a temporary workbook for testing.');
        }

        try {
            if (file_put_contents($path, $workbook) !== strlen($workbook)) {
                $this->fail('Unable to write the temporary workbook for testing.');
            }

            $archive = new \ZipArchive;

            if ($archive->open($path) !== true) {
                $this->fail('The report export is not a valid XLSX archive.');
            }

            try {
                $contents = $archive->getFromName($entry);

                if ($contents === false) {
                    $this->fail("The workbook is missing entry [{$entry}].");
                }

                return $contents;
            } finally {
                $archive->close();
            }
        } finally {
            if (is_file($path) && ! unlink($path)) {
                $this->fail('Unable to remove the temporary workbook used by the test.');
            }
        }
    }
}
