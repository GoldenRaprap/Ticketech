<?php

namespace Tests\Feature;

use App\Models\ClientConductPhrase;
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
            ->assertSee('Matched indicators')
            ->assertSee('View ticket')
            ->assertSee(route('staff.ticket-detail', $ticket), false);

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

    public function test_admin_can_add_update_and_disable_screening_phrases(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = $this->createTicket();

        $this->actingAs($admin)
            ->post(route('admin.moderation.phrases.store'), [
                'phrase' => 'Custom--marker phrase',
                'reason' => 'abusive',
            ])
            ->assertRedirect(route('admin.moderation'));

        $phrase = ClientConductPhrase::query()->where('phrase', 'custom marker phrase')->firstOrFail();
        $this->assertSame('abusive', $phrase->reason);
        $this->assertTrue($phrase->is_active);

        $this->from(route('admin.moderation'))
            ->post(route('admin.moderation.phrases.store'), [
                'phrase' => 'CUSTOM marker--phrase',
                'reason' => 'abusive',
            ])
            ->assertRedirect(route('admin.moderation'))
            ->assertSessionHasErrors('phrase');

        $this->get(route('admin.moderation'))
            ->assertOk()
            ->assertSee('Message screening phrases')
            ->assertSee('custom marker phrase')
            ->assertSee('Disable');

        $this->put(route('admin.moderation.phrases.update', $phrase), [
            'phrase' => 'Another custom marker',
            'reason' => 'threatening',
        ])->assertRedirect(route('admin.moderation'));

        $phrase->refresh();
        $this->assertSame('another custom marker', $phrase->phrase);
        $this->assertSame('threatening', $phrase->reason);

        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => $ticket->requester_email,
            'body' => 'This has ANOTHER--custom marker in it.',
        ])->assertRedirect(route('ticket.check'));

        $report = TicketMisuseReport::query()->firstOrFail();
        $this->assertSame('threatening', $report->reason);
        $this->assertStringContainsString('another custom marker', $report->details);

        $this->patch(route('admin.moderation.phrases.activation', $phrase), [
            'is_active' => '0',
        ])->assertRedirect(route('admin.moderation'));

        $this->assertFalse($phrase->fresh()->is_active);
        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => $ticket->requester_email,
            'body' => 'Another custom marker',
        ])->assertRedirect(route('ticket.check'));

        $this->assertDatabaseCount('ticket_misuse_reports', 1);
    }

    public function test_non_admin_cannot_manage_screening_phrases(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('admin.moderation.phrases.store'), [
                'phrase' => 'custom marker phrase',
                'reason' => 'abusive',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('client_conduct_phrases', [
            'phrase' => 'custom marker phrase',
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
