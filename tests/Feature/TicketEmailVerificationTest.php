<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PendingTicketSubmission;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketRequesterUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_verification_sends_a_pdf_ticket_confirmation_once(): void
    {
        Notification::fake();
        $token = Str::random(64);
        $ticket = $this->createPendingTicket($token);
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'We are investigating your request.',
            'is_internal' => false,
        ]);
        $ticket->comments()->create([
            'author_id' => $staff->id,
            'author_name' => $staff->name,
            'body' => 'Staff-only investigation details.',
            'is_internal' => true,
        ]);
        $ticket->events()->create([
            'actor_id' => $staff->id,
            'event' => 'status_changed',
            'description' => 'Status changed from New to In Progress.',
        ]);
        $verificationUrl = $this->verificationUrl($ticket, $token, now()->addHour());

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSeeText('Email Verified')
            ->assertSeeText($ticket->ticket_number);

        $this->get(route('ticket.check'))
            ->assertOk()
            ->assertSee('value="'.$ticket->ticket_number.'"', false)
            ->assertSee('value="jamie@example.edu"', false);

        $ticket->refresh();
        $this->assertNotNull($ticket->requester_email_verified_at);
        $this->assertNull($ticket->requester_email_verification_token_hash);
        $this->assertNull($ticket->requester_email_verification_expires_at);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'requester_email_verified',
        ]);

        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            function (TicketRequesterUpdate $notification, array $channels, AnonymousNotifiable $notifiable) use ($ticket): bool {
                if ($notification->emailSubject !== 'Your support ticket is confirmed') {
                    return false;
                }

                $mail = $notification->toMail($notifiable);

                $this->assertCount(1, $mail->rawAttachments);
                $this->assertSame('ticket-'.$ticket->ticket_number.'.pdf', $mail->rawAttachments[0]['name']);
                $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime']);
                $pdfContent = $mail->rawAttachments[0]['data'];
                $this->assertStringStartsWith('%PDF-1.4', $pdfContent);
                $this->assertStringEndsWith('%%EOF', $pdfContent);
                $this->assertStringContainsString('(Current status) Tj', $pdfContent);
                $this->assertStringContainsString('(New) Tj', $pdfContent);
                $this->assertStringContainsString('Cannot access \\(Bluebook\\)', $pdfContent);
                $this->assertStringContainsString('TICKET DETAILS', $pdfContent);
                $this->assertStringContainsString('CLIENT INFORMATION', $pdfContent);
                $this->assertStringContainsString('TICKET CONVERSATION', $pdfContent);
                $this->assertStringNotContainsString('Priority:', $pdfContent);
                $this->assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF$/', $pdfContent, $crossReferenceMatch));
                $this->assertSame('xref', substr($pdfContent, (int) $crossReferenceMatch[1], 4));

                return $channels === ['mail']
                    && $notifiable->routeNotificationFor('mail') === $ticket->requester_email
                    && $notification->emailSubject === 'Your support ticket is confirmed';
            },
        );
        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            fn (TicketRequesterUpdate $notification): bool => $notification->updateMessage === 'We are investigating your request.',
        );
        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            fn (TicketRequesterUpdate $notification): bool => $notification->updateMessage === 'A status update was made: Status changed from New to In Progress.',
        );

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSeeText('Email Already Verified');

        Notification::assertSentOnDemandTimes(TicketRequesterUpdate::class, 3);
    }

    public function test_signed_link_with_a_different_token_does_not_verify_the_email(): void
    {
        Notification::fake();
        $token = Str::random(64);
        $ticket = $this->createPendingTicket($token);
        $verificationUrl = $this->verificationUrl($ticket, Str::random(64), now()->addHour());

        $this->get($verificationUrl)->assertForbidden();

        $this->assertNull($ticket->fresh()->requester_email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_expired_signed_link_does_not_verify_the_email(): void
    {
        Notification::fake();
        $token = Str::random(64);
        $ticket = $this->createPendingTicket($token);
        $verificationUrl = $this->verificationUrl($ticket, $token, now()->subMinute());

        $this->get($verificationUrl)->assertForbidden();

        $this->assertNull($ticket->fresh()->requester_email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_expired_database_challenge_is_rejected_with_a_valid_signature(): void
    {
        Notification::fake();
        $token = Str::random(64);
        $ticket = $this->createPendingTicket($token, now()->subMinute());
        $verificationUrl = $this->verificationUrl($ticket, $token, now()->addHour());

        $this->get($verificationUrl)->assertForbidden();

        $this->assertNull($ticket->fresh()->requester_email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_invalid_pending_submission_token_does_not_create_a_ticket(): void
    {
        Notification::fake();
        $submission = $this->createPendingSubmission(Str::random(64));
        $verificationUrl = $this->pendingVerificationUrl($submission, Str::random(64), now()->addHour());

        $this->get($verificationUrl)->assertForbidden();
        $this->post($verificationUrl)->assertForbidden();

        $this->assertDatabaseCount('tickets', 0);
        Notification::assertNothingSent();
    }

    public function test_revisiting_a_completed_pending_submission_link_reports_the_ticket_as_already_approved(): void
    {
        Notification::fake();
        $token = Str::random(64);
        $submission = $this->createPendingSubmission($token);
        $verificationUrl = $this->pendingVerificationUrl($submission, $token, now()->addHour());

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSeeText('Confirm Your Email');

        $this->post($verificationUrl)
            ->assertRedirect(route('ticket.submitted'));

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame($ticket->ticket_number, $submission->fresh()->completed_ticket_number);
        Notification::assertSentOnDemandTimes(TicketRequesterUpdate::class, 1);

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSeeText('Ticket Already Approved')
            ->assertSeeText($ticket->ticket_number);

        $this->post($verificationUrl)
            ->assertOk()
            ->assertSeeText('Ticket Already Approved')
            ->assertSeeText($ticket->ticket_number);

        $this->assertDatabaseCount('tickets', 1);
        Notification::assertSentOnDemandTimes(TicketRequesterUpdate::class, 1);
    }

    public function test_expired_pending_submission_cannot_create_a_ticket(): void
    {
        Notification::fake();
        $token = Str::random(64);
        $submission = $this->createPendingSubmission($token);
        $verificationUrl = $this->pendingVerificationUrl($submission, $token, now()->subMinute());

        $this->get($verificationUrl)->assertForbidden();
        $this->post($verificationUrl)->assertForbidden();

        $this->assertDatabaseCount('tickets', 0);
        Notification::assertNothingSent();
    }

    public function test_expired_pending_submission_cleanup_removes_private_attachments(): void
    {
        Storage::fake('private');
        $attachmentPath = 'ticket-attachments/pending-evidence.pdf';
        Storage::disk('private')->put($attachmentPath, 'private ticket evidence');
        $submission = $this->createPendingSubmission(Str::random(64), now()->subMinute(), [
            [
                'original_name' => 'evidence.pdf',
                'path' => $attachmentPath,
                'mime_type' => 'application/pdf',
                'size_bytes' => 23,
            ],
        ]);

        $this->artisan('tickets:expire-pending-email-verifications')
            ->assertSuccessful();

        $this->assertDatabaseMissing('pending_ticket_submissions', ['id' => $submission->id]);
        Storage::disk('private')->assertMissing($attachmentPath);
    }

    public function test_expired_completed_submission_cleanup_preserves_ticket_attachments(): void
    {
        Storage::fake('private');
        $attachmentPath = 'ticket-attachments/verified-evidence.pdf';
        Storage::disk('private')->put($attachmentPath, 'verified ticket evidence');
        $ticket = $this->createPendingTicket(Str::random(64));
        $ticket->attachments()->create([
            'original_name' => 'evidence.pdf',
            'disk' => 'private',
            'path' => $attachmentPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 24,
        ]);
        $submission = $this->createPendingSubmission(Str::random(64), now()->subMinute(), [
            [
                'original_name' => 'evidence.pdf',
                'path' => $attachmentPath,
                'mime_type' => 'application/pdf',
                'size_bytes' => 24,
            ],
        ]);
        $submission->forceFill(['completed_ticket_number' => $ticket->ticket_number])->save();

        $this->artisan('tickets:expire-pending-email-verifications')
            ->assertSuccessful();

        $this->assertDatabaseMissing('pending_ticket_submissions', ['id' => $submission->id]);
        Storage::disk('private')->assertExists($attachmentPath);
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'path' => $attachmentPath,
        ]);
    }

    private function createPendingSubmission(string $token, ?Carbon $expiresAt = null, array $attachments = []): PendingTicketSubmission
    {
        return PendingTicketSubmission::create([
            'requester_name' => 'Jamie Student',
            'requester_email' => 'jamie@example.edu',
            'payload' => [
                'student_id' => 'PCU-2026-001',
                'requester_phone' => null,
                'department_id' => Department::factory()->create()->id,
                'category_id' => null,
                'subject' => 'Cannot access Bluebook',
                'description' => 'The course page returns an error.',
                'moderation_warning' => false,
            ],
            'attachments' => $attachments,
            'verification_token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt ?? now()->addDay(),
        ]);
    }

    private function pendingVerificationUrl(
        PendingTicketSubmission $submission,
        string $token,
        \DateTimeInterface $expiresAt,
    ): string {
        return URL::temporarySignedRoute(
            'ticket.email.confirm',
            $expiresAt,
            ['submission' => $submission->id, 'token' => $token],
        );
    }

    private function createPendingTicket(string $token, ?Carbon $expiresAt = null): Ticket
    {
        $department = Department::factory()->create();

        return Ticket::create([
            'ticket_number' => 'ED-2026-000001',
            'department_id' => $department->id,
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-2026-001',
            'requester_email' => 'jamie@example.edu',
            'requester_email_verification_token_hash' => hash('sha256', $token),
            'requester_email_verification_expires_at' => $expiresAt ?? now()->addDay(),
            'subject' => 'Cannot access (Bluebook)',
            'description' => 'The course page returns an error.',
            'priority' => 'Low',
            'status' => 'New',
        ]);
    }

    private function verificationUrl(Ticket $ticket, string $token, \DateTimeInterface $expiresAt): string
    {
        return URL::temporarySignedRoute(
            'ticket.email.verify',
            $expiresAt,
            ['ticket' => $ticket->ticket_number, 'token' => $token],
        );
    }
}
