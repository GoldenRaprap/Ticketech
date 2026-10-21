<?php

namespace Tests\Feature;

use App\Mail\ClientConductWarning;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffWorkspaceFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_queue_search_filters_database_tickets(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $matchingTicket = $this->createTicket('ED-2026-000301', 'Network is offline');
        $this->createTicket('ED-2026-000302', 'Cannot reset my password');

        $this->actingAs($staff)
            ->get(route('staff.tickets', ['search' => 'ED-2026-000301']))
            ->assertOk()
            ->assertSee($matchingTicket->ticket_number)
            ->assertDontSee('ED-2026-000302');
    }

    public function test_ticket_queue_filters_by_status_priority_and_assignee(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $assignee = User::factory()->create(['role' => 'staff']);
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
            ->assertSee($matchingTicket->ticket_number)
            ->assertDontSee('ED-2026-000312')
            ->assertDontSee('ED-2026-000313');
    }

    public function test_staff_can_update_ticket_status_priority_and_assignee(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $assignee = User::factory()->create(['role' => 'staff']);
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
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $assignee->id,
            'ticket_id' => $ticket->id,
            'type' => 'assignment',
        ]);
    }

    public function test_ticket_update_preserves_assignee_when_field_is_omitted(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $assignee = User::factory()->create(['role' => 'staff']);
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
        $staff = User::factory()->create(['role' => 'staff']);
        $assignee = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('ED-2026-000316', 'Resolved request');

        $this->actingAs($staff)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Resolved',
                'priority' => 'High',
                'assigned_to_user_id' => $assignee->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Assigned')
            ->assertSee('Resolved');

        $ticket = $ticket->fresh();
        $this->assertInstanceOf(Carbon::class, $ticket->assigned_at);
        $this->assertInstanceOf(Carbon::class, $ticket->resolved_at);
    }

    public function test_staff_ticket_detail_detects_new_replies_and_restricts_update_checks_to_staff(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
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
        $staff = User::factory()->create(['role' => 'staff']);
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
                'student_id' => '2023******',
                'email' => 'JAMIE@example.edu',
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
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('ED-2026-000320', 'Ticket timing test');

        $this->travelTo('2026-10-21 10:45:00');
        $this->actingAs($staff)
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'Assigned',
                'priority' => 'Low',
                'assigned_to_user_id' => $staff->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->travelTo('2026-10-21 12:30:00');
        $ticket->refresh();
        $this->actingAs($staff)
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
        $this->actingAs($staff)
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
        $staff = User::factory()->create(['role' => 'staff']);
        $requester = User::factory()->create(['role' => 'requester']);
        $ticket = $this->createTicket('ED-2026-000304', 'Portal access issue');

        $this->actingAs($staff)
            ->from(route('staff.ticket-detail', $ticket))
            ->put(route('staff.ticket-update', $ticket), [
                'status' => 'In Progress',
                'priority' => 'High',
                'assigned_to_user_id' => $requester->id,
            ])
            ->assertRedirect(route('staff.ticket-detail', $ticket))
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->assertSame('New', $ticket->fresh()->status);
        $this->assertDatabaseCount('ticket_events', 0);
    }

    public function test_staff_can_add_internal_ticket_notes_without_assignment_notification(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $assignee = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('ED-2026-000305', 'Portal access issue', $assignee->id);

        $this->actingAs($staff)
            ->post(route('staff.ticket-comments.store', $ticket), [
                'body' => 'Escalated to the platform team.',
                'is_internal' => '1',
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
        $this->assertDatabaseCount('workspace_notifications', 0);
    }

    public function test_staff_ticket_activity_separates_public_replies_and_internal_notes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
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
        $staff = User::factory()->create(['role' => 'staff']);
        $assignee = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('ED-2026-000315', 'Portal access issue', $assignee->id);

        $this->actingAs($staff)
            ->post(route('staff.ticket-comments.store', $ticket), [
                'body' => 'We have received your request and are investigating.',
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
    }

    public function test_staff_attachment_download_is_scoped_to_its_ticket(): void
    {
        Storage::fake('private');
        $staff = User::factory()->create(['role' => 'staff']);
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

    public function test_staff_can_create_public_knowledge_article_and_draft_is_hidden(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $articleData = [
            'title' => 'Connect to campus Wi-Fi',
            'category' => 'Network',
            'summary' => 'Set up a device to use campus Wi-Fi.',
            'body' => "Open your device's wireless settings.\nChoose the campus network.",
            'is_published' => '1',
        ];

        $this->actingAs($staff)
            ->post(route('staff.knowledge-base.store'), $articleData)
            ->assertSessionHasNoErrors();

        $article = KnowledgeArticle::query()->firstOrFail();
        $this->assertSame($staff->id, $article->author_id);
        $this->assertTrue($article->is_published);

        $this->get(route('knowledge-base'))
            ->assertOk()
            ->assertSee($article->title);
        $this->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee('Choose the campus network.');

        $this->actingAs($staff)
            ->put(route('staff.knowledge-base.update', $article), [
                ...$articleData,
                'title' => 'Updated campus Wi-Fi guide',
                'body' => 'Use your campus account to connect.',
            ])
            ->assertRedirect(route('staff.knowledge-base'));

        $article->refresh();
        $this->assertSame('updated-campus-wi-fi-guide', $article->slug);
        $this->assertSame('Use your campus account to connect.', $article->body);
        $this->get(route('knowledge-base.show', $article->slug))
            ->assertOk()
            ->assertSee($article->body);

        $draft = KnowledgeArticle::create([
            'title' => 'Internal setup procedure',
            'slug' => 'internal-setup-procedure',
            'category' => 'Internal',
            'summary' => 'Staff-only setup details.',
            'body' => 'Do not display this draft publicly.',
            'author_id' => $staff->id,
            'is_published' => false,
        ]);

        $this->get(route('knowledge-base'))
            ->assertDontSee($draft->title);
        $this->get(route('knowledge-base.show', $draft->slug))
            ->assertNotFound();

        $this->actingAs($staff)
            ->delete(route('staff.knowledge-base.destroy', $article))
            ->assertRedirect(route('staff.knowledge-base'));
        $this->assertModelMissing($article);
    }

    public function test_reports_are_calculated_from_ticket_records(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->createTicket('ED-2026-000308', 'New network issue', null, 'Urgent');
        $this->createTicket('ED-2026-000309', 'Resolved account issue', null, 'Low', 'Resolved');

        $this->actingAs($staff)
            ->get(route('staff.reports', ['period' => 'all']))
            ->assertOk()
            ->assertViewHas('totalTicketCount', 2)
            ->assertViewHas('openTicketCount', 1)
            ->assertViewHas('resolvedTicketCount', 1)
            ->assertSee('Tickets by priority')
            ->assertSee('Urgent');
    }

    public function test_admin_audit_logs_show_real_ticket_events_and_filter_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
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
            ->assertSee(route('staff.ticket-detail', $ticket), false)
            ->assertDontSee($otherTicket->ticket_number)
            ->assertDontSee('Priority changed to Urgent.')
            ->assertViewHas('events', fn ($events): bool => $events->total() === 1 && $events->first()->id === $event->id);
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

    public function test_staff_can_update_profile_and_email_must_be_unique(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        User::factory()->create(['email' => 'already-used@pcu.edu.ph']);

        $this->actingAs($staff)
            ->put(route('staff.profile.update'), [
                'name' => 'Taylor Updated',
                'email' => 'taylor.updated@pcu.edu.ph',
            ])
            ->assertRedirect(route('staff.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Taylor Updated',
            'email' => 'taylor.updated@pcu.edu.ph',
            'email_verified_at' => null,
        ]);

        $this->actingAs($staff->fresh())
            ->from(route('staff.profile'))
            ->put(route('staff.profile.update'), [
                'name' => 'Taylor Updated',
                'email' => 'already-used@pcu.edu.ph',
            ])
            ->assertRedirect(route('staff.profile'))
            ->assertSessionHasErrors('email');
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
}
