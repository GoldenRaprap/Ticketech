<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TicketNotificationRecipients
{
    /**
     * @return Collection<int, User>
     */
    public function forNewTicket(
        Ticket $ticket,
        ?int $requesterUserId = null,
        ?int $assigneeUserId = null,
    ): Collection {
        return User::query()
            ->whereIn('role', User::STAFF_ACCESS_ROLES)
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->where(function (Builder $query) use ($ticket): void {
                $query->where('role', 'admin');

                if ($ticket->department_id !== null) {
                    $query->orWhere(function (Builder $departmentQuery) use ($ticket): void {
                        $departmentQuery
                            ->whereIn('role', ['staff', 'department_admin'])
                            ->where('department_id', $ticket->department_id);
                    });
                } else {
                    $query->orWhere(function (Builder $departmentAdminQuery): void {
                        $departmentAdminQuery
                            ->where('role', 'department_admin')
                            ->whereNotNull('department_id');
                    });
                }
            })
            ->when(
                $requesterUserId !== null,
                fn (Builder $query) => $query->whereKeyNot($requesterUserId),
            )
            ->when(
                $assigneeUserId !== null,
                fn (Builder $query) => $query->whereKeyNot($assigneeUserId),
            )
            ->get(['id']);
    }

    /**
     * @return Collection<int, User>
     */
    public function forTicket(Ticket $ticket, ?int $exceptUserId = null): Collection
    {
        return User::query()
            ->whereIn('role', User::STAFF_ACCESS_ROLES)
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->where(function (Builder $query) use ($ticket): void {
                $query->where('role', 'admin')
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('role', 'department_admin')
                            ->whereNotNull('department_id');
                    });

                if ($ticket->department_id !== null) {
                    $query->orWhere('department_id', $ticket->department_id);
                }
            })
            ->when($exceptUserId !== null, fn (Builder $query) => $query->whereKeyNot($exceptUserId))
            ->get(['id']);
    }

    /**
     * @return Collection<int, User>
     */
    public function forEscalation(Ticket $ticket): Collection
    {
        return User::query()
            ->whereIn('role', ['admin', 'department_admin'])
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->where(function (Builder $query) use ($ticket): void {
                $query->where('role', 'admin');

                if ($ticket->department_id !== null) {
                    $query->orWhere(function (Builder $departmentQuery) use ($ticket): void {
                        $departmentQuery
                            ->where('role', 'department_admin')
                            ->where('department_id', $ticket->department_id);
                    });
                }
            })
            ->get(['id']);
    }
}
