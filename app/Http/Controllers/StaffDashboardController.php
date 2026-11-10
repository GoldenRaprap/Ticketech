<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $openStatuses = ['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold'];
        $visibleTickets = Ticket::query()->visibleTo($user);
        $statusCounts = (clone $visibleTickets)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('total', 'status');

        $recentTickets = (clone $visibleTickets)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('staff.dashboard', [
            'openTicketCount' => (clone $visibleTickets)->whereIn('status', $openStatuses)->count(),
            'newTicketCount' => $statusCounts->get('New', 0),
            'unassignedTicketCount' => (clone $visibleTickets)
                ->whereIn('status', $openStatuses)
                ->whereNull('assigned_to_user_id')
                ->count(),
            'assignedToMeCount' => (clone $visibleTickets)
                ->whereIn('status', $openStatuses)
                ->where('assigned_to_user_id', $request->user()->id)
                ->count(),
            'statusCounts' => $statusCounts,
            'recentTickets' => $recentTickets,
        ]);
    }
}
