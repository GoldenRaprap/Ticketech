<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceNotification;
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

        $notifications = WorkspaceNotification::query()
            ->with('ticket:id,ticket_number')
            ->where('user_id', $request->user()->id)
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filter === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('staff.notifications', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => WorkspaceNotification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        WorkspaceNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return to_route('staff.notifications')->with('status', 'All notifications marked as read.');
    }

    public function markRead(Request $request, WorkspaceNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->update(['read_at' => now()]);

        if ($notification->type === 'client_report' && $request->user()->isAdmin()) {
            return to_route('admin.moderation');
        }

        return $notification->ticket
            ? to_route('staff.ticket-detail', $notification->ticket)
            : to_route('staff.notifications');
    }
}
