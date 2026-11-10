<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkspaceNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'read' => ['nullable', 'in:all,unread,read'],
        ])['read'] ?? 'all';

        $notifications = $this->visibleNotifications($request->user())
            ->with('ticket:id,ticket_number')
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filter === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('staff.notifications', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $this->visibleNotifications($request->user())
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->visibleNotifications($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return to_route('staff.notifications')->with('status', 'All notifications marked as read.');
    }

    public function markRead(Request $request, WorkspaceNotification $notification): RedirectResponse
    {
        $ticket = $notification->ticket;
        abort_unless(
            $notification->user_id === $request->user()->id
                && ($ticket === null || $ticket->isVisibleTo($request->user())),
            404,
        );

        $notification->update(['read_at' => now()]);

        if ($notification->type === 'client_report' && $request->user()->isAdmin()) {
            return to_route('admin.moderation');
        }

        return $notification->ticket
            ? to_route('staff.ticket-detail', $notification->ticket)
            : to_route('staff.notifications');
    }

    /**
     * @return Builder<WorkspaceNotification>
     */
    private function visibleNotifications(User $user): Builder
    {
        return WorkspaceNotification::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $query) use ($user): void {
                $query->whereNull('ticket_id')
                    ->orWhereHas('ticket', fn (Builder $tickets) => $tickets->visibleTo($user));
            });
    }
}
