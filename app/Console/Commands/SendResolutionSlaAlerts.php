<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Notifications\TicketRequesterUpdate;
use App\Services\ImmediateNotificationSender;
use App\Services\SlaRescueAssessor;
use App\Services\TicketNotificationRecipients;
use App\Services\TicketResolutionSla;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

#[Signature('tickets:send-resolution-sla-alerts')]
#[Description('Assess ticket resolution risk and notify relevant people before and after SLA deadlines')]
class SendResolutionSlaAlerts extends Command
{
    public function handle(
        TicketResolutionSla $resolutionSla,
        TicketNotificationRecipients $notificationRecipients,
        SlaRescueAssessor $rescueAssessor,
        ImmediateNotificationSender $notificationSender,
    ): int {
        $resolutionSla->initializeMissingDeadlines();

        $notificationCount = 0;

        Ticket::query()
            ->whereIn('status', ['New', 'Assigned', 'In Progress'])
            ->whereNotNull('resolution_due_at')
            ->chunkById(100, function (EloquentCollection $tickets) use (
                $notificationRecipients,
                $notificationSender,
                $resolutionSla,
                $rescueAssessor,
                &$notificationCount,
            ): void {
                $assessments = $rescueAssessor->assessBatch($tickets, $resolutionSla);

                foreach ($tickets as $ticket) {
                    $assessment = $assessments[$ticket->id];
                    $cycleStart = $ticket->created_at;
                    $ticketRecipients = null;

                    if ($assessment['score'] >= SlaRescueAssessor::MINIMUM_RISK_SCORE) {
                        $riskReasons = collect($assessment['factors'])
                            ->map(fn (string $factor): string => SlaRescueAssessor::factorLabels()[$factor])
                            ->implode(', ');
                        $ticketRecipients = $notificationRecipients->forTicket($ticket);
                        $notificationCount += $this->notifyUsersOnce(
                            $ticket,
                            'sla_at_risk',
                            'Ticket at risk of missing its resolution target',
                            "{$ticket->ticket_number} · {$ticket->subject} is at risk of missing its resolution target. Risk factors: {$riskReasons}.",
                            $ticketRecipients,
                            $cycleStart,
                        );
                    }

                    if (
                        $ticket->resolution_warning_at !== null
                        && $ticket->resolution_warning_at->lessThanOrEqualTo(now())
                    ) {
                        $ticketRecipients ??= $notificationRecipients->forTicket($ticket);

                        if ($ticketRecipients->isEmpty()) {
                            $this->warn("Ticket [{$ticket->ticket_number}] has an SLA warning but no active assignee or administrator to notify.");
                        } else {
                            $isOverdue = $ticket->resolution_due_at->lessThanOrEqualTo(now());
                            $alertType = $isOverdue ? 'sla_overdue' : 'sla_warning';
                            $dueAt = $ticket->resolution_due_at
                                ->copy()
                                ->timezone(config('app.display_timezone'))
                                ->format('M j, g:i A');
                            $message = $isOverdue
                                ? "{$ticket->ticket_number} · {$ticket->subject} is past its resolution target (due {$dueAt})."
                                : "{$ticket->ticket_number} · {$ticket->subject} is approaching its resolution target (due {$dueAt}).";
                            $title = $isOverdue
                                ? 'Ticket resolution target overdue'
                                : 'Ticket resolution target approaching';

                            $notificationCount += $this->notifyUsersOnce(
                                $ticket,
                                $alertType,
                                $title,
                                $message,
                                $ticketRecipients,
                                $cycleStart,
                            );
                        }
                    }

                    if ($ticket->resolution_due_at->lessThanOrEqualTo(now())) {
                        $this->sendRequesterBreachUpdate($ticket, $notificationSender, $cycleStart);

                        $escalationStart = $assessment['last_staff_activity_at'] !== null
                            && $assessment['last_staff_activity_at']->greaterThan($ticket->resolution_due_at)
                            ? $assessment['last_staff_activity_at']
                            : CarbonImmutable::instance($ticket->resolution_due_at);

                        if ($resolutionSla->businessMinutesBetween($escalationStart, now()) >= 30) {
                            $notificationCount += $this->notifyUsersOnce(
                                $ticket,
                                'sla_escalation',
                                'Overdue ticket needs attention',
                                "{$ticket->ticket_number} · {$ticket->subject} has been overdue for at least 30 business minutes without staff activity.",
                                $notificationRecipients->forEscalation($ticket),
                                $escalationStart,
                            );
                        }
                    }
                }
            });

        $this->info("Created {$notificationCount} SLA rescue notification(s).");

        return self::SUCCESS;
    }

    /**
     * @param  EloquentCollection<int, User>  $recipients
     */
    private function notifyUsersOnce(
        Ticket $ticket,
        string $type,
        string $title,
        string $message,
        EloquentCollection $recipients,
        CarbonInterface $dedupeSince,
    ): int {
        $recipientIds = $recipients->modelKeys();

        if ($recipientIds === []) {
            return 0;
        }

        $alreadyNotified = WorkspaceNotification::query()
            ->where('ticket_id', $ticket->id)
            ->where('type', $type)
            ->where('created_at', '>=', $dedupeSince)
            ->whereIn('user_id', $recipientIds)
            ->pluck('user_id')
            ->all();
        $createdCount = 0;

        foreach (array_diff($recipientIds, $alreadyNotified) as $recipientId) {
            WorkspaceNotification::create([
                'user_id' => $recipientId,
                'ticket_id' => $ticket->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
            ]);
            $createdCount++;
        }

        return $createdCount;
    }

    private function sendRequesterBreachUpdate(
        Ticket $ticket,
        ImmediateNotificationSender $notificationSender,
        CarbonInterface $cycleStart,
    ): void {
        if (
            ! $ticket->canEmailRequester()
            || TicketEvent::query()
                ->where('ticket_id', $ticket->id)
                ->where('event', 'sla_requester_update_sent')
                ->where('created_at', '>=', $cycleStart)
                ->exists()
        ) {
            return;
        }

        $notificationSender->sendToEmail(
            $ticket->requester_email,
            new TicketRequesterUpdate(
                requesterName: $ticket->requester_name,
                ticketNumber: $ticket->ticket_number,
                ticketSubject: $ticket->subject,
                updateMessage: 'We are sorry, but the resolution target for your ticket has passed. Our team is working on your request and will provide an update as soon as possible.',
                emailSubject: 'Your support ticket is taking longer than expected',
            ),
        );

        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'actor_name' => 'SLA Rescue automation',
            'event' => 'sla_requester_update_sent',
            'description' => 'An automated email was sent to the requester after the resolution target was missed.',
        ]);
    }
}
