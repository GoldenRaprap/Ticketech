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
use App\Services\TicketEmailAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_requester_update_email_pauses_ticket_replies(): void
    {
        $notification = new TicketRequesterUpdate(
            'Jamie Student',
            'ED-2026-000001',
            'Cannot access the portal',
            'Your ticket was updated.',
            'Your support ticket was updated',
        );

        $notification->failed(new RuntimeException('SMTP transport is unavailable.'));

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket.submission.enabled',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket.replies.enabled',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => TicketEmailAvailability::DELIVERY_STATUS_SETTING,
            'value' => TicketEmailAvailability::DELIVERY_UNAVAILABLE_STATUS,
        ]);
    }

    public function test_failed_workspace_email_pauses_ticket_email_features(): void
    {
        $notification = new WorkspaceAlert(new WorkspaceNotification);

        $notification->failed(new RuntimeException('SMTP transport is unavailable.'));

        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket.submission.enabled',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting' => 'ticket.replies.enabled',
            'value' => '0',
        ]);
    }

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

    public function test_ticket_submission_notifies_only_its_department_queue_after_email_confirmation(): void
    {
        Notification::fake();
        $department = Department::factory()->create();
        $departmentAdmin = User::factory()->create([
            'role' => 'department_admin',
            'department_id' => $department->id,
        ]);
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);
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
            ->assertSeeText('Check your inbox')
            ->assertSeeText('Confirm your email')
            ->assertSeeText('Ticket submitted')
            ->assertSee('class="ticket-verification-steps"', false)
            ->assertSee('aria-current="step"', false)
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
            'user_id' => $departmentAdmin->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'user_id' => $staff->id,
            'ticket_id' => $ticket->id,
            'type' => 'new_ticket',
        ]);
        Notification::assertSentTo($departmentAdmin, WorkspaceAlert::class);
        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            function (TicketRequesterUpdate $notification): bool {
                if ($notification->emailSubject !== 'Your support ticket is confirmed'
                    || $notification->ticketPdf === null
                    || ! str_starts_with($notification->ticketPdf, '%PDF-1.4')) {
                    return false;
                }

                $this->assertStringContainsString('TICKET DETAILS', $notification->ticketPdf);
                $this->assertStringContainsString('CLIENT INFORMATION', $notification->ticketPdf);
                $this->assertStringContainsString('TICKET CONVERSATION', $notification->ticketPdf);
                $this->assertStringContainsString('Cannot access Bluebook', $notification->ticketPdf);
                $this->assertStringNotContainsString('Priority:', $notification->ticketPdf);

                return true;
            },
        );

        $this->post($verificationUrl)->assertNotFound();
        $this->assertDatabaseCount('tickets', 1);
        Notification::assertSentOnDemandTimes(TicketRequesterUpdate::class, 1);
    }

    public function test_public_staff_reply_emails_the_requester_but_internal_notes_do_not(): void
    {
        Notification::fake();
        $ticket = $this->createTicket('jamie@example.edu');
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $ticket->department_id,
        ]);

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
        $ticket = $this->createTicket('jamie@example.edu');
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $ticket->department_id,
        ]);

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
        $ticket = $this->createTicket('jamie@example.edu', false);
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $ticket->department_id,
        ]);

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

        $email = $this->sendNotificationAndGetEmail($notification);
        $renderedMail = (string) $email->getHtmlBody();

        $this->assertStringContainsString('&lt;script&gt;', $renderedMail);
        $this->assertStringNotContainsString($dangerousMessage, $renderedMail);
        $this->assertStringContainsString('src="cid:ticketech-logo@ticketech"', $renderedMail);
        $this->assertStringContainsString('src="cid:pcu-logo@ticketech"', $renderedMail);
        $this->assertStringContainsString('alt="TickeTech"', $renderedMail);
        $this->assertStringContainsString('alt="Philippine Christian University logo"', $renderedMail);
        $this->assertStringContainsString('width="240"', $renderedMail);
        $this->assertStringContainsString('max-height: 80px', $renderedMail);
        $this->assertMatchesRegularExpression(
            '/<p[^>]*align="center"[^>]*>\s*<img src="cid:pcu-logo@ticketech"[^>]*width="90"[^>]*margin: 0 auto;/s',
            $renderedMail,
        );
        $this->assertTrue(
            strpos($renderedMail, 'src="cid:pcu-logo@ticketech"') > strpos($renderedMail, 'Please do not reply'),
        );
        $this->assertStringNotContainsString(asset('images/Philippine_Christian_University_logo.png'), $renderedMail);
        $this->assertCount(2, $email->getAttachments());
    }

    public function test_email_verification_and_workspace_alerts_include_both_logos(): void
    {
        $verificationEmail = $this->sendNotificationAndGetEmail(new TicketEmailVerification(
            'Jamie Student',
            'https://example.edu/verify',
            'November 6, 2026 at 10:00 AM',
        ));
        $workspaceRecipient = User::factory()->make();
        $workspaceEmail = $this->sendNotificationAndGetEmail(
            new WorkspaceAlert(new WorkspaceNotification([
                'title' => 'New support ticket',
                'message' => 'A requester needs help.',
            ])),
            $workspaceRecipient,
        );
        $verificationMail = (string) $verificationEmail->getHtmlBody();
        $workspaceMail = (string) $workspaceEmail->getHtmlBody();

        $this->assertSame(1, substr_count($verificationMail, 'src="cid:ticketech-logo@ticketech"'));
        $this->assertSame(1, substr_count($verificationMail, 'src="cid:pcu-logo@ticketech"'));
        $this->assertSame(1, substr_count($workspaceMail, 'src="cid:ticketech-logo@ticketech"'));
        $this->assertSame(1, substr_count($workspaceMail, 'src="cid:pcu-logo@ticketech"'));
        $this->assertStringContainsString('alt="TickeTech"', $verificationMail);
        $this->assertStringContainsString('alt="Philippine Christian University logo"', $verificationMail);
        $this->assertStringContainsString('alt="TickeTech"', $workspaceMail);
        $this->assertStringContainsString('alt="Philippine Christian University logo"', $workspaceMail);
        $this->assertMatchesRegularExpression(
            '/<p[^>]*align="center"[^>]*>\s*<img src="cid:pcu-logo@ticketech"[^>]*width="90"[^>]*margin: 0 auto;/s',
            $verificationMail,
        );
        $this->assertMatchesRegularExpression(
            '/<p[^>]*align="center"[^>]*>\s*<img src="cid:pcu-logo@ticketech"[^>]*width="90"[^>]*margin: 0 auto;/s',
            $workspaceMail,
        );
        $this->assertTrue(
            strpos($verificationMail, 'src="cid:pcu-logo@ticketech"') > strpos($verificationMail, 'If you did not submit this request'),
        );
        $this->assertTrue(
            strpos($workspaceMail, 'src="cid:pcu-logo@ticketech"') > strpos($workspaceMail, 'A requester needs help.'),
        );
    }

    private function sendNotificationAndGetEmail(
        \Illuminate\Notifications\Notification $notification,
        ?User $notifiable = null,
    ): Email {
        config()->set('mail.default', 'array');

        if ($notifiable) {
            $notifiable->notifyNow($notification);
        } else {
            Notification::route('mail', 'recipient@example.edu')->notifyNow($notification);
        }

        $sentMessage = Mail::mailer('array')->getSymfonyTransport()->messages()->last();
        $email = $sentMessage->getOriginalMessage();

        $this->assertInstanceOf(Email::class, $email);

        return $email;
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
