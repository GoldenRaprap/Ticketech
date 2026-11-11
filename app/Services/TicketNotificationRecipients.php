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
}
