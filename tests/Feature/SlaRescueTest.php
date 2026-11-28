<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Notifications\TicketRequesterUpdate;
use App\Services\SlaSettings;
use App\Services\TicketResolutionSla;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaRescueTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_flags_predictive_risk_before_the_standard_warning_and_suggests_a_handoff(): void
    {
        $this->useUtcBusinessTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $recommendedStaff = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'staff',
        ]);
        $this->travelTo('2026-11-02 09:00:00 UTC');
        $ticket = $this->createTicket($department, null, 'Medium');

        Notification::fake();
        $this->travelTo('2026-11-02 10:00:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $ticket->refresh();
        $this->assertSame(70, $ticket->sla_risk_score);
        $this->assertSame(
            ['no_recent_staff_activity', 'unassigned'],
            $ticket->sla_risk_factors,
        );
        $this->assertTrue($ticket->resolution_warning_at->greaterThan(now()));
        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'type' => 'sla_at_risk',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'sla_warning',
        ]);

        $this->actingAs($admin)
            ->get(route('staff.tickets'))
            ->assertOk()
            ->assertSee('At risk');
        $this->actingAs($admin)
            ->get(route('staff.ticket-detail', $ticket))
            ->assertOk()
            ->assertSee('Suggested handoff')
            ->assertSee($recommendedStaff->name);
    }

    public function test_monitor_emails_requester_once_and_escalates_after_thirty_business_minutes_without_staff_activity(): void
    {
        $this->useUtcBusinessTime();
        $this->setUrgentResolutionTarget(1, 'business_hour');
        $department = Department::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $departmentAdmin = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'department_admin',
        ]);
        $otherDepartmentAdmin = User::factory()->create([
            'department_id' => Department::factory()->create()->id,
            'role' => 'department_admin',
        ]);
        $staff = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'staff',
        ]);
        $this->travelTo('2026-11-02 09:00:00 UTC');
        $ticket = $this->createTicket($department, $staff, 'Urgent', 'In Progress');

        Notification::fake();
        $this->travelTo('2026-11-02 09:59:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseMissing('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'sla_requester_update_sent',
        ]);
        Notification::assertSentOnDemandTimes(TicketRequesterUpdate::class, 0);

        $this->travelTo('2026-11-02 10:00:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'event' => 'sla_requester_update_sent',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'sla_escalation',
        ]);

        $this->travelTo('2026-11-02 10:20:00 UTC');
        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'actor_id' => $staff->id,
            'event' => 'internal_note_added',
            'description' => 'Staff reviewed the overdue ticket.',
        ]);
        $this->travelTo('2026-11-02 10:35:00 UTC');
        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'event' => 'requester_reply',
            'description' => 'The requester provided more information.',
        ]);

        $this->travelTo('2026-11-02 10:45:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'type' => 'sla_escalation',
        ]);

        $this->travelTo('2026-11-02 10:50:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $departmentAdmin->id,
            'type' => 'sla_escalation',
        ]);
        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'type' => 'sla_escalation',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'type' => 'sla_escalation',
        ]);
        $this->assertDatabaseMissing('workspace_notifications', [
            'ticket_id' => $ticket->id,
            'user_id' => $otherDepartmentAdmin->id,
            'type' => 'sla_escalation',
        ]);

        $this->travelTo('2026-11-02 10:55:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertSame(
            2,
            WorkspaceNotification::query()
                ->where('ticket_id', $ticket->id)
                ->where('type', 'sla_escalation')
                ->count(),
        );
        $this->assertSame(
            1,
            TicketEvent::query()
                ->where('ticket_id', $ticket->id)
                ->where('event', 'sla_requester_update_sent')
                ->count(),
        );

        $this->travelTo('2026-11-02 11:00:00 UTC');
        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'actor_id' => $staff->id,
            'event' => 'status_changed',
            'description' => 'Staff updated the ticket status.',
        ]);
        $this->travelTo('2026-11-02 11:30:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertSame(
            4,
            WorkspaceNotification::query()
                ->where('ticket_id', $ticket->id)
                ->where('type', 'sla_escalation')
                ->count(),
        );

        $this->travelTo('2026-11-02 11:35:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $this->assertSame(
            4,
            WorkspaceNotification::query()
                ->where('ticket_id', $ticket->id)
                ->where('type', 'sla_escalation')
                ->count(),
        );

        Notification::assertSentOnDemand(
            TicketRequesterUpdate::class,
            fn (
                TicketRequesterUpdate $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ): bool => $channels === ['mail']
                && $notifiable->routeNotificationFor('mail') === $ticket->requester_email
                && $notification->emailSubject === 'Your support ticket is taking longer than expected',
        );
    }

    public function test_monitor_uses_assignee_workload_to_flag_a_near_deadline_ticket(): void
    {
        $this->useUtcBusinessTime();
        $this->setUrgentResolutionTarget(1, 'business_hour');
        $department = Department::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'staff',
        ]);
        $lowerWorkloadStaff = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'staff',
        ]);
        $this->travelTo('2026-11-02 09:00:00 UTC');
        $tickets = [];

        for ($index = 0; $index < 5; $index++) {
            $tickets[] = $this->createTicket(
                $department,
                $staff,
                $index === 0 ? 'Urgent' : 'Medium',
                'In Progress',
            );
        }

        Notification::fake();
        $this->travelTo('2026-11-02 09:05:00 UTC');
        $this->artisan('tickets:send-resolution-sla-alerts')->assertSuccessful();

        $atRiskTicket = $tickets[0]->fresh();
        $this->assertSame(65, $atRiskTicket->sla_risk_score);
        $this->assertSame(['deadline_approaching', 'high_assignee_workload'], $atRiskTicket->sla_risk_factors);
        $this->assertDatabaseHas('workspace_notifications', [
            'ticket_id' => $atRiskTicket->id,
            'user_id' => $admin->id,
            'type' => 'sla_at_risk',
        ]);
        $this->actingAs($admin)
            ->get(route('staff.ticket-detail', $atRiskTicket))
            ->assertOk()
            ->assertSee('Suggested handoff')
            ->assertSee($lowerWorkloadStaff->name);
        $this->assertSame($staff->id, $atRiskTicket->fresh()->assigned_to_user_id);
    }

    public function test_reports_show_sla_breaches_and_at_risk_reasons_for_the_selected_period(): void
    {
        $this->useUtcBusinessTime();
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::factory()->create();
        $this->travelTo('2026-11-02 09:00:00 UTC');
        $breachedTicket = $this->createTicket($department, null, 'High', 'Resolved');
        $breachedTicket->forceFill([
            'resolution_due_at' => CarbonImmutable::parse('2026-11-02 10:00:00 UTC'),
            'resolved_at' => CarbonImmutable::parse('2026-11-02 10:30:00 UTC'),
        ])->save();
        $atRiskTicket = $this->createTicket($department, null, 'Medium');
        $atRiskTicket->forceFill([
            'sla_risk_score' => 70,
            'sla_risk_factors' => ['unassigned', 'no_recent_staff_activity'],
        ])->save();

        $this->actingAs($admin)
            ->get(route('staff.reports', [
                'period' => 'daily',
                'reference_date' => '2026-11-02',
            ]))
            ->assertOk()
            ->assertViewHas('slaBreachCount', 1)
            ->assertViewHas('slaAtRiskTicketCount', 1)
            ->assertSee('SLA breaches')
            ->assertSee('At-risk tickets')
            ->assertSee('No assignee')
            ->assertSee('No staff activity for 60 business minutes');
    }

    private function useUtcBusinessTime(): void
    {
        config(['app.display_timezone' => 'UTC', 'app.timezone' => 'UTC']);
    }

    private function setUrgentResolutionTarget(int $value, string $unit): void
    {
        $targets = app(SlaSettings::class)->targets();
        $targets['urgent']['resolution'] = ['value' => $value, 'unit' => $unit];

        app(SlaSettings::class)->update($targets);
    }

    private function createTicket(
        Department $department,
        ?User $assignee,
        string $priority,
        string $status = 'New',
    ): Ticket {
        $ticket = Ticket::create([
            'ticket_number' => 'ED-2026-'.str_pad((string) (Ticket::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'department_id' => $department->id,
            'assigned_to_user_id' => $assignee?->id,
            'requester_name' => 'Jamie Student',
            'student_id' => 'PCU-001',
            'requester_email' => 'jamie@example.edu',
            'subject' => 'Support request',
            'description' => 'A test support request.',
            'priority' => $priority,
            'status' => $status,
            'assigned_at' => $assignee === null ? null : now(),
            'first_assigned_at' => $assignee === null ? null : now(),
        ]);
        app(TicketResolutionSla::class)->refresh($ticket);
        $ticket->save();

        return $ticket;
    }
}
