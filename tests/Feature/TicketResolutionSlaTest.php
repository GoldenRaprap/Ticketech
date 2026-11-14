<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Services\SlaSettings;
use App\Services\TicketResolutionSla;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketResolutionSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolution_warning_is_sent_once_to_the_assignee_and_admins_at_eighty_percent(): void
    {
        $this->setUrgentResolutionTarget(1, 'business_hour');
        $assignee = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $otherStaff = User::factory()->create(['role' => 'staff']);
        $this->travelTo('2026-11-02 01:00:00 UTC');
        $ticket = $this->createTicket($assignee->id, 'Urgent');
        $this->setTicketCycleStart($ticket, CarbonImmutable::parse('2026-11-02 01:00:00 UTC'));

        $this->travelTo('2026-11-02 01:47:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'sla_warning',
        ]);

        $this->travelTo('2026-11-02 01:48:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $assignee->id,
            'type' => 'sla_warning',
            'title' => 'Ticket resolution target approaching',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'type' => 'sla_warning',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $otherStaff->id,
            'type' => 'sla_warning',
        ]);
        $this->assertSame(
            2,
            WorkspaceNotification::query()->where('ticket_id', $ticket->id)->where('type', 'sla_warning')->count(),
        );

        $this->travelTo('2026-11-02 02:00:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertSame(
            2,
            WorkspaceNotification::query()->where('ticket_id', $ticket->id)->where('type', 'sla_overdue')->count(),
        );
    }

    public function test_unassigned_ticket_sla_warning_is_sent_to_admins(): void
    {
        $this->setUrgentResolutionTarget(1, 'business_hour');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-11-02 01:00:00 UTC');
        $ticket = $this->createTicket(null, 'Urgent');
        $this->setTicketCycleStart($ticket, CarbonImmutable::parse('2026-11-02 01:00:00 UTC'));
        $this->travelTo('2026-11-02 01:48:00 UTC');

        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'type' => 'sla_warning',
        ]);
    }

    public function test_business_day_resolution_deadline_skips_weekends_and_nonbusiness_hours(): void
    {
        $this->travelTo('2026-11-06 08:00:00 UTC');
        $ticket = $this->createTicket(null, 'Urgent');

        app(TicketResolutionSla::class)->refresh($ticket);
        $ticket->save();

        $this->assertSame('2026-11-09 03:12:00', $ticket->resolution_warning_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 04:00:00', $ticket->resolution_due_at->format('Y-m-d H:i:s'));
    }

    public function test_resolution_clock_pauses_on_waiting_status_and_resumes_with_remaining_time(): void
    {
        $this->setUrgentResolutionTarget(1, 'business_hour');
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-11-02 01:00:00 UTC');
        $ticket = $this->createTicket($staff->id, 'Urgent', 'In Progress');
        $this->setTicketCycleStart($ticket, CarbonImmutable::parse('2026-11-02 01:00:00 UTC'));

        $this->travelTo('2026-11-02 01:30:00 UTC');
        $this->actingAs($staff->fresh())
            ->put(route('staff.ticket-update', $ticket), ['status' => 'Waiting for User'])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->travelTo('2026-11-02 01:50:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'type' => 'sla_warning',
        ]);

        $this->travelTo('2026-11-02 03:30:00 UTC');
        $this->actingAs($staff->fresh())
            ->put(route('staff.ticket-update', $ticket), ['status' => 'In Progress'])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $ticket->refresh();
        $this->assertSame(120, $ticket->sla_paused_minutes);
        $this->assertNull($ticket->sla_paused_at);
        $this->assertSame('2026-11-02 03:48:00', $ticket->resolution_warning_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 04:00:00', $ticket->resolution_due_at->format('Y-m-d H:i:s'));

        $this->travelTo('2026-11-02 03:48:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'type' => 'sla_warning',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'type' => 'sla_warning',
        ]);
    }

    public function test_admin_target_changes_recalculate_open_ticket_deadlines(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-11-02 01:00:00 UTC');
        $ticket = $this->createTicket(null, 'Urgent');
        $this->setTicketCycleStart($ticket, CarbonImmutable::parse('2026-11-02 01:00:00 UTC'));
        $targets = app(SlaSettings::class)->targets();
        $targets['urgent']['resolution'] = ['value' => 1, 'unit' => 'business_hour'];

        $this->actingAs($admin)
            ->put(route('admin.sla-settings.update'), ['targets' => $targets])
            ->assertRedirect(route('admin.sla-settings'));

        $this->assertSame(
            '2026-11-02 02:00:00',
            $ticket->fresh()->resolution_due_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_resolved_and_closed_tickets_cannot_be_reopened_by_staff(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        foreach (['Resolved', 'Closed'] as $status) {
            $ticket = $this->createTicket($staff->id, 'Urgent', $status);
            $resolvedAt = now()->subDay();
            $ticket->update([
                'resolved_at' => $resolvedAt,
                'last_resolved_at' => $resolvedAt,
            ]);

            $this->actingAs($staff->fresh())
                ->from(route('staff.ticket-detail', $ticket))
                ->put(route('staff.ticket-update', $ticket), ['status' => 'In Progress'])
                ->assertRedirect(route('staff.ticket-detail', $ticket))
                ->assertSessionHasErrors([
                    'status' => 'Resolved or closed tickets cannot be reopened.',
                ]);

            $ticket->refresh();
            $this->assertSame($status, $ticket->status);
            $this->assertSame($resolvedAt->toDateTimeString(), $ticket->resolved_at->toDateTimeString());
            $this->assertDatabaseMissing('ticket_events', [
                'ticket_id' => $ticket->id,
                'event' => 'status_changed',
                'description' => "Status changed from {$status} to In Progress.",
            ]);
            $this->assertDatabaseMissing('workspace_notifications', [
                'ticket_id' => $ticket->id,
                'type' => 'status_update',
            ]);
        }
    }

    public function test_requester_reply_resumes_waiting_ticket_and_sla_clock(): void
    {
        $this->setUrgentResolutionTarget(1, 'business_hour');
        $staff = User::factory()->create(['role' => 'staff']);
        $this->travelTo('2026-11-02 01:00:00 UTC');
        $ticket = $this->createTicket($staff->id, 'Urgent', 'In Progress');
        $this->setTicketCycleStart($ticket, CarbonImmutable::parse('2026-11-02 01:00:00 UTC'));
        $this->travelTo('2026-11-02 01:30:00 UTC');

        $this->actingAs($staff->fresh())
            ->put(route('staff.ticket-update', $ticket), ['status' => 'Waiting for User'])
            ->assertRedirect(route('staff.ticket-detail', $ticket));

        $this->travelTo('2026-11-02 02:30:00 UTC');
        $this->post(route('ticket.reply'), [
            'ticket' => $ticket->ticket_number,
            'email' => 'jamie@example.edu',
            'body' => 'I have provided the requested details.',
        ])->assertRedirect(route('ticket.check'));

        $ticket->refresh();
        $this->assertSame('In Progress', $ticket->status);
        $this->assertSame(60, $ticket->sla_paused_minutes);
        $this->assertNull($ticket->sla_paused_at);
        $this->assertSame('2026-11-02 03:00:00', $ticket->resolution_due_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'description' => 'Ticket resumed after a requester reply (Waiting for User → In Progress).',
        ]);
    }

    public function test_ticket_queue_sorts_active_sla_risks_first_and_completed_tickets_last(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $overdue = $this->createTicket($staff->id, 'Low', 'New');
        $approaching = $this->createTicket($staff->id, 'Low', 'Assigned');
        $urgentHealthy = $this->createTicket($staff->id, 'Urgent', 'In Progress');
        $waiting = $this->createTicket($staff->id, 'Urgent', 'On Hold');
        $resolved = $this->createTicket($staff->id, 'Urgent', 'Resolved');

        $overdue->forceFill([
            'resolution_warning_at' => now()->subHour(),
            'resolution_due_at' => now()->subMinute(),
        ])->save();
        $approaching->forceFill([
            'resolution_warning_at' => now()->subMinute(),
            'resolution_due_at' => now()->addHour(),
        ])->save();
        $urgentHealthy->forceFill([
            'resolution_warning_at' => now()->addHour(),
            'resolution_due_at' => now()->addHours(3),
        ])->save();
        $waiting->forceFill([
            'resolution_warning_at' => now()->subHour(),
            'resolution_due_at' => now()->subMinute(),
        ])->save();
        $resolved->forceFill([
            'resolution_warning_at' => now()->subHour(),
            'resolution_due_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($staff->fresh())
            ->get(route('staff.tickets'))
            ->assertOk()
            ->assertSeeInOrder([
                $overdue->ticket_number,
                $approaching->ticket_number,
                $urgentHealthy->ticket_number,
                $waiting->ticket_number,
                $resolved->ticket_number,
            ])
            ->assertSee('Resolution target')
            ->assertSee('Due soon')
            ->assertSee('Paused')
            ->assertSee('Overdue');
    }

    private function setUrgentResolutionTarget(int $value, string $unit): void
    {
        $targets = app(SlaSettings::class)->targets();
        $targets['urgent']['resolution'] = ['value' => $value, 'unit' => $unit];

        app(SlaSettings::class)->update($targets);
    }

    private function setTicketCycleStart(Ticket $ticket, CarbonImmutable $cycleStart): void
    {
        $ticket->forceFill([
            'created_at' => $cycleStart,
            'updated_at' => $cycleStart,
        ]);
        app(TicketResolutionSla::class)->refresh($ticket, $cycleStart);
        $ticket->save();
    }

    private function createTicket(?int $assigneeId, string $priority, string $status = 'New'): Ticket
    {
        $departmentId = null;

        if ($assigneeId !== null) {
            $assignee = User::query()->findOrFail($assigneeId);
            $departmentId = $assignee->department_id;

            if ($departmentId === null) {
                $departmentId = Department::factory()->create()->id;
                $assignee->update(['department_id' => $departmentId]);
            }
        } else {
            $departmentId = Department::factory()->create()->id;
        }

        return Ticket::create([
            'ticket_number' => 'ED-2026-'.str_pad((string) (Ticket::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'department_id' => $departmentId,
            'assigned_to_user_id' => $assigneeId,
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Support request',
            'description' => 'A test support request.',
            'priority' => $priority,
            'status' => $status,
        ]);
    }
}
