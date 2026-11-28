<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TicketWorkloadAssigner
{
    /**
     * @var list<string>
     */
    public const ACTIVE_STATUSES = [
        'New',
        'Assigned',
        'In Progress',
        'Waiting for User',
        'On Hold',
    ];

    public function assign(Ticket $ticket): ?User
    {
        if ($ticket->department_id === null) {
            return null;
        }

        $assignee = $this->eligibleAssignees($ticket->department_id)
            ->orderBy('active_tickets_count')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($assignee === null) {
            return null;
        }

        $ticket->assigned_to_user_id = $assignee->id;
        $ticket->assigned_at = now();
        $ticket->first_assigned_at ??= $ticket->assigned_at;
        $ticket->status = 'Assigned';

        return $assignee;
    }

    public function recommendHandoff(Ticket $ticket): ?User
    {
        if ($ticket->department_id === null) {
            return null;
        }

        $assignees = $this->eligibleAssignees($ticket->department_id)
            ->orderBy('active_tickets_count')
            ->orderBy('id')
            ->get();

        if ($assignees->isEmpty()) {
            return null;
        }

        if ($ticket->assigned_to_user_id === null) {
            return $assignees->first();
        }

        $currentAssignee = $assignees->firstWhere('id', $ticket->assigned_to_user_id);

        if ($currentAssignee === null) {
            return $assignees->first();
        }

        return $assignees->first(
            fn (User $assignee): bool => $assignee->id !== $currentAssignee->id
                && $assignee->active_tickets_count < $currentAssignee->active_tickets_count,
        );
    }

    private function eligibleAssignees(int $departmentId): Builder
    {
        return User::query()
            ->whereIn('role', ['staff', 'department_admin'])
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->where('department_id', $departmentId)
            ->withCount([
                'assignedTickets as active_tickets_count' => fn (Builder $query) => $query
                    ->whereIn('status', self::ACTIVE_STATUSES),
            ]);
    }
}
