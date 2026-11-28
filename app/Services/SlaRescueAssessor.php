<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class SlaRescueAssessor
{
    public const MINIMUM_RISK_SCORE = 60;

    private const DEADLINE_WINDOW_MINUTES = 120;

    private const STAFF_INACTIVITY_MINUTES = 60;

    private const HIGH_WORKLOAD_TICKET_COUNT = 5;

    /**
     * @var array<string, int>
     */
    private const FACTOR_SCORES = [
        'deadline_approaching' => 35,
        'no_recent_staff_activity' => 30,
        'unassigned' => 40,
        'high_assignee_workload' => 30,
    ];

    /**
     * @var list<string>
     */
    private const STAFF_ACTIVITY_EVENTS = [
        'status_changed',
        'comment_added',
        'internal_note_added',
    ];

    /**
     * @var array<string, string>
     */
    private const FACTOR_LABELS = [
        'deadline_approaching' => 'SLA warning overdue or due within two business hours',
        'no_recent_staff_activity' => 'No staff activity for 60 business minutes',
        'unassigned' => 'No assignee',
        'high_assignee_workload' => 'Assignee has five or more active tickets',
    ];

    /**
     * @param  EloquentCollection<int, Ticket>  $tickets
     * @return array<int, array{
     *     score: int,
     *     factors: list<string>,
     *     last_staff_activity_at: CarbonImmutable|null
     * }>
     */
    public function assessBatch(
        EloquentCollection $tickets,
        TicketResolutionSla $resolutionSla,
    ): array {
        $now = CarbonImmutable::now('UTC');
        $ticketIds = $tickets->modelKeys();
        $assigneeIds = $tickets->pluck('assigned_to_user_id')->filter()->unique()->values();
        $workloadByAssignee = $this->activeWorkload($assigneeIds);
        $activityByTicket = $this->latestStaffActivity($ticketIds);
        $assessments = [];

        foreach ($tickets as $ticket) {
            $lastStaffActivityAt = $activityByTicket->get($ticket->id);
            $lastRelevantActivityAt = $lastStaffActivityAt !== null
                && $lastStaffActivityAt->greaterThan($ticket->created_at)
                ? $lastStaffActivityAt
                : CarbonImmutable::instance($ticket->created_at);
            $factors = [];

            if ($ticket->resolution_warning_at !== null) {
                $minutesUntilWarning = $ticket->resolution_warning_at->lessThanOrEqualTo($now)
                    ? 0
                    : $resolutionSla->businessMinutesBetween($now, $ticket->resolution_warning_at);

                if ($minutesUntilWarning <= self::DEADLINE_WINDOW_MINUTES) {
                    $factors[] = 'deadline_approaching';
                }
            }

            if (
                $resolutionSla->businessMinutesBetween($lastRelevantActivityAt, $now)
                >= self::STAFF_INACTIVITY_MINUTES
            ) {
                $factors[] = 'no_recent_staff_activity';
            }

            if ($ticket->assigned_to_user_id === null) {
                $factors[] = 'unassigned';
            } elseif (
                (int) $workloadByAssignee->get($ticket->assigned_to_user_id, 0)
                >= self::HIGH_WORKLOAD_TICKET_COUNT
            ) {
                $factors[] = 'high_assignee_workload';
            }

            $score = min(100, array_sum(array_map(
                fn (string $factor): int => self::FACTOR_SCORES[$factor],
                $factors,
            )));
            $ticket->forceFill([
                'sla_risk_score' => $score,
                'sla_risk_factors' => $factors,
            ]);

            if ($ticket->isDirty(['sla_risk_score', 'sla_risk_factors'])) {
                $ticket->save();
            }

            $assessments[$ticket->id] = [
                'score' => $score,
                'factors' => $factors,
                'last_staff_activity_at' => $lastStaffActivityAt,
            ];
        }

        return $assessments;
    }

    /**
     * @return array<string, string>
     */
    public static function factorLabels(): array
    {
        return self::FACTOR_LABELS;
    }

    /**
     * @param  Collection<int, int|string>  $assigneeIds
     * @return Collection<int, int>
     */
    private function activeWorkload(Collection $assigneeIds): Collection
    {
        if ($assigneeIds->isEmpty()) {
            return collect();
        }

        return Ticket::query()
            ->whereIn('assigned_to_user_id', $assigneeIds)
            ->whereIn('status', TicketWorkloadAssigner::ACTIVE_STATUSES)
            ->selectRaw('assigned_to_user_id, COUNT(*) as active_ticket_count')
            ->groupBy('assigned_to_user_id')
            ->pluck('active_ticket_count', 'assigned_to_user_id')
            ->map(fn (int|string $count): int => (int) $count);
    }

    /**
     * @param  list<int>  $ticketIds
     * @return Collection<int, CarbonImmutable>
     */
    private function latestStaffActivity(array $ticketIds): Collection
    {
        if ($ticketIds === []) {
            return collect();
        }

        return TicketEvent::query()
            ->join('users', 'users.id', '=', 'ticket_events.actor_id')
            ->whereIn('ticket_events.ticket_id', $ticketIds)
            ->whereIn('ticket_events.event', self::STAFF_ACTIVITY_EVENTS)
            ->whereIn('users.role', User::STAFF_ACCESS_ROLES)
            ->select('ticket_events.ticket_id')
            ->selectRaw('MAX(ticket_events.created_at) as latest_activity_at')
            ->groupBy('ticket_events.ticket_id')
            ->get()
            ->mapWithKeys(fn (object $event): array => [
                (int) $event->ticket_id => CarbonImmutable::parse($event->latest_activity_at, 'UTC'),
            ]);
    }
}
