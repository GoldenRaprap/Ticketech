<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Notifications\TicketEmailVerification;
use App\Notifications\TicketRequesterUpdate;
use App\Notifications\WorkspaceAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_alerts_are_sent_to_the_notified_staff_member_by_email(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);

        WorkspaceNotification::create([
            'user_id' => $staff->id,
            'type' => 'sla_warning',
            'title' => 'Ticket resolution target approaching',
            'message' => 'ED-2026-000001 is approaching its resolution target.',
        ]);

        Notification::assertSentTo(
            $staff,
            WorkspaceAlert::class,
            fn (WorkspaceAlert $notification, array $channels): bool => $channels === ['mail']
                && $notification->workspaceNotification->title === 'Ticket resolution target approaching',
        );
    }

    public function test_ticket_submission_waits_for_email_confirmation_before_creating_a_ticket(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $department = Department::factory()->create();
        $category = Category::factory()->create();

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
            'email' => 'jamie@example.edu',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'subject' => 'Cannot access Bluebook',
            'message' => 'The course page returns an error.',
        ])->assertRedirect(route('ticket.submitted'));

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('workspace_notifications', 0);
        $this->assertDatabaseHas('pending_ticket_submissions', [
            'requester_email' => 'jamie@example.edu',
        ]);

        $verificationUrl = null;
        Notification::assertSentOnDemand(
            TicketEmailVerification::class,
            function (TicketEmailVerification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$verificationUrl): bool {
                $verificationUrl = $notification->verificationUrl;

                return $channels === ['mail']
                && $notifiable->routeNotificationFor('mail') === 'jamie@example.edu'
                && str_contains($notification->verificationUrl, 'signature=');
            },
        );
        $this->assertNotNull($verificationUrl);

        $this->get(route('ticket.submitted'))
            ->assertOk()
            ->assertSeeText('Your ticket has not been created or sent to staff yet.')
            ->assertDontSeeText('Ticket Number');

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSeeText('Confirm Email and Submit Ticket');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('workspace_notifications', 0);

        $this->post($verificationUrl)
            ->assertRedirect(route('ticket.submitted'));

        $ticket = Ticket::query()->firstOrFail();
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'requester_email' => 'jamie@example.edu',
            'status' => 'New',
        ]);
        $this->assertNotNull($ticket->requester_email_verified_at);
        $this->assertDatabaseMissing('pending_ticket_submissions', [
            'requester_email' => 'jamie@example.edu',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        Notification::assertSentTo($staff, WorkspaceAlert::class);
        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            fn (TicketRequesterUpdate $notification): bool => $notification->emailSubject === 'Your support ticket is confirmed'
                && $notification->ticketPdf !== null
                && str_starts_with($notification->ticketPdf, '%PDF-1.4'),
        );

        $this->post($verificationUrl)->assertNotFound();
        $this->assertDatabaseCount('tickets', 1);
        Notification::assertSentOnDemandTimes(TicketRequesterUpdate::class, 1);
    }

    public function test_public_staff_reply_emails_the_requester_but_internal_notes_do_not(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('jamie@example.edu');

        $this->actingAs($staff)
            ->post(route('staff.ticket-comments.store', $ticket), [
                'body' => 'Internal troubleshooting details.',
                'is_internal' => '1',
            ])->assertRedirect(route('staff.ticket-detail', $ticket));

        Notification::assertNothingSent();

        $this->post(route('staff.ticket-comments.store', $ticket), [
            'body' => 'We have restored access to the course page.',
        ])->assertRedirect(route('staff.ticket-detail', $ticket));

        Notification::assertSentOnDemandTimes(TicketRequesterUpdate::class, 1);
        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            fn (TicketRequesterUpdate $notification): bool => $notification->updateMessage === 'We have restored access to the course page.',
        );
    }

    public function test_ticket_status_changes_email_the_requester(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('jamie@example.edu');

        $this->actingAs($staff)
            ->put(route('staff.ticket-update', $ticket), ['status' => 'Resolved'])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            fn (TicketRequesterUpdate $notification): bool => $notification->updateMessage === 'The status of your support ticket changed from New to Resolved.',
        );
    }

    public function test_staff_replies_and_status_changes_do_not_email_an_unverified_requester(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = $this->createTicket('jamie@example.edu', false);

        $this->actingAs($staff)
            ->post(route('staff.ticket-comments.store', $ticket), [
                'body' => 'We have restored access to the course page.',
            ])->assertRedirect(route('staff.ticket-detail', $ticket));

        Notification::assertNothingSent();

        $this->put(route('staff.ticket-update', $ticket), ['status' => 'Resolved'])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        Notification::assertNothingSent();
    }

    public function test_requester_email_renders_user_content_as_escaped_markdown(): void
    {
        $dangerousMessage = '<script>alert("xss")</script>';
        $notification = new TicketRequesterUpdate(
            'Jamie Student',
            'ED-2026-000001',
            'Cannot access Bluebook',
            $dangerousMessage,
            'A reply was added to your support ticket',
        );

        $renderedMail = $notification->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('&lt;script&gt;', $renderedMail);
        $this->assertStringNotContainsString($dangerousMessage, $renderedMail);
    }

    private function createTicket(string $requesterEmail, bool $emailVerified = true): Ticket
    {
        $department = Department::factory()->create();

        return Ticket::create([
            'ticket_number' => 'ED-2026-000001',
            'department_id' => $department->id,
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
            'requester_email' => $requesterEmail,
            'requester_email_verified_at' => $emailVerified ? now() : null,
            'requester_email_verification_token_hash' => $emailVerified ? null : hash('sha256', 'pending-verification-token'),
            'requester_email_verification_expires_at' => $emailVerified ? null : now()->addDay(),
            'subject' => 'Cannot access Bluebook',
            'description' => 'The course page returns an error.',
            'priority' => 'Low',
            'status' => 'New',
        ]);
    }
}
