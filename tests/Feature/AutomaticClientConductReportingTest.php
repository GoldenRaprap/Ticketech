<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketMisuseReport;
use App\Models\User;
use App\Notifications\TicketEmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutomaticClientConductReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_ticket_with_a_direct_threat_creates_a_pending_report_for_admin_review(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->post(route('ticket.store'), [
            'name' => 'Jamie Student',
            'student_id' => '2023000001',
            'email' => 'jamie@example.edu',
            'subject' => "I'll hurt you",
            'message' => "I'll hurt you if this is not fixed.",
        ])->assertRedirect(route('ticket.submitted'));

        $this->assertDatabaseCount('ticket_misuse_reports', 0);
        $this->confirmPendingEmail();

        $ticket = Ticket::query()->firstOrFail();
        $report = TicketMisuseReport::query()->firstOrFail();

        $this->assertSame($ticket->id, $report->ticket_id);
        $this->assertSame('threatening', $report->reason);
        $this->assertSame('pending', $report->status);
        $this->assertNull($report->reporter_id);
        $this->assertStringContainsString('initial ticket submission', $report->details);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $admin->id,
            'ticket_id' => $ticket->id,
            'type' => 'client_report',
            'title' => 'Automated client conduct report',
        ]);
        $this->assertDatabaseMissing('client_moderations', [
            'email' => 'jamie@example.edu',
            'cleared_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.moderation'))
            ->assertOk()
            ->assertSee('Automated screening')
            ->assertSee('Automated flag')
            ->assertSee('Matched indicators');

        $this->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Reported by Automated screening');

        $this->patch(route('admin.moderation.review', $report), ['action' => 'dismiss'])
            ->assertRedirect(route('admin.moderation'));

        $this->assertDatabaseHas('ticket_misuse_reports', [
            'id' => $report->id,
            'status' => 'reviewed',
            'action' => 'dismiss',
            'reviewed_by_id' => $admin->id,
        ]);
    }

    public function test_abusive_requester_reply_creates_a_pending_report_without_restricting_the_client(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket();

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => $ticket->requester_email,
            'body' => 'You are an idiot and you are useless.',
        ])->assertRedirect(route('ticket.check'));

        $report = TicketMisuseReport::query()->firstOrFail();

        $this->assertSame('abusive', $report->reason);
        $this->assertSame('pending', $report->status);
        $this->assertNull($report->reporter_id);
        $this->assertStringContainsString('requester reply', $report->details);
        $this->assertDatabaseHas('workspace_notifications', [
            'user_id' => $admin->id,
            'ticket_id' => $ticket->id,
            'type' => 'client_report',
        ]);
        $this->assertDatabaseMissing('client_moderations', [
            'email' => $ticket->requester_email,
            'cleared_at' => null,
        ]);
    }

    public function test_frustrated_but_non_abusive_requester_reply_does_not_create_a_report(): void
    {
        Notification::fake();
        $ticket = $this->createTicket();

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => $ticket->requester_email,
            'body' => 'I am frustrated that the portal is unavailable. Please help.',
        ])->assertRedirect(route('ticket.check'));

        $this->assertDatabaseCount('ticket_misuse_reports', 0);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'body' => 'I am frustrated that the portal is unavailable. Please help.',
            'is_internal' => false,
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'client_report',
        ]);
    }

    private function confirmPendingEmail(): void
    {
        $verificationUrl = null;

        Notification::assertSentOnDemand(
            TicketEmailVerification::class,
            function (
                TicketEmailVerification $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ) use (&$verificationUrl): bool {
                $verificationUrl = $notification->verificationUrl;

                return $channels === ['mail']
                    && $notifiable->routeNotificationFor('mail') !== null;
            },
        );

        $this->assertNotNull($verificationUrl);
        $this->get($verificationUrl)->assertOk();
        $this->post($verificationUrl)->assertRedirect(route('ticket.submitted'));
    }

    private function createTicket(): Ticket
    {
        $department = Department::factory()->create();

        return Ticket::create([
            'ticket_number' => 'ED-2026-000001',
            'department_id' => $department->id,
            'requester_name' => 'Jamie Student',
            'student_id' => '2023000001',
            'requester_email' => 'jamie@example.edu',
            'requester_email_verified_at' => now(),
            'subject' => 'Cannot sign in',
            'description' => 'The portal does not accept my password.',
            'priority' => 'High',
            'status' => 'In Progress',
        ]);
    }
}
