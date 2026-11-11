<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\WorkspaceNotification;
use App\Services\TicketNotificationRecipients;
use App\Services\TicketResolutionSla;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

#[Signature('tickets:send-resolution-sla-alerts')]
#[Description('Notify ticket owners when resolution targets are nearing or past due')]
class SendResolutionSlaAlerts extends Command
{
    public function handle(
        TicketResolutionSla $resolutionSla,
        TicketNotificationRecipients $notificationRecipients,
    ): int {
        $resolutionSla->initializeMissingDeadlines();

        $notificationCount = 0;

        Ticket::query()
            ->whereIn('status', ['New', 'Assigned', 'In Progress'])
            ->whereNotNull('resolution_warning_at')
            ->where('resolution_warning_at', '<=', now())
            ->chunkById(100, function (EloquentCollection $tickets) use ($notificationRecipients, &$notificationCount): void {
                foreach ($tickets as $ticket) {
                    if ($ticket->resolution_due_at === null) {
                        throw new LogicException("Ticket [{$ticket->id}] has an alert time but no resolution deadline.");
                    }

                    $cycleStart = $ticket->last_reopened_at ?? $ticket->created_at;
                    $recipientIds = $notificationRecipients->forTicket($ticket)->modelKeys();

                    if ($recipientIds === []) {
                        $this->warn("Ticket [{$ticket->ticket_number}] has an SLA warning but no active assignee or administrator to notify.");

                        continue;
                    }

                    $isOverdue = $ticket->resolution_due_at->lessThanOrEqualTo(now());
                    $alertType = $isOverdue ? 'sla_overdue' : 'sla_warning';
                    $alreadyNotified = WorkspaceNotification::query()
                        ->where('ticket_id', $ticket->id)
                        ->where('type', $alertType)
                        ->where('created_at', '>=', $cycleStart)
                        ->whereIn('user_id', $recipientIds)
                        ->pluck('user_id')
                        ->all();

                    $title = $isOverdue
                        ? 'Ticket resolution target overdue'
                        : 'Ticket resolution target approaching';
                    $dueAt = $ticket->resolution_due_at
                        ->copy()
                        ->timezone(config('app.display_timezone'))
                        ->format('M j, g:i A');
                    $message = $isOverdue
                        ? "{$ticket->ticket_number} · {$ticket->subject} is past its resolution target (due {$dueAt})."
                        : "{$ticket->ticket_number} · {$ticket->subject} is approaching its resolution target (due {$dueAt}).";

                    foreach (array_diff($recipientIds, $alreadyNotified) as $recipientId) {
                        WorkspaceNotification::create([
                            'user_id' => $recipientId,
                            'ticket_id' => $ticket->id,
                            'type' => $alertType,
                            'title' => $title,
                            'message' => $message,
                        ]);
                        $notificationCount++;
                    }
                }
            });

        $this->info("Created {$notificationCount} resolution SLA alert(s).");

        return self::SUCCESS;
    }
}
