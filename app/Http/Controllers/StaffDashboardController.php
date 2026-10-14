<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $openStatuses = ['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold'];
        $statusCounts = Ticket::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('total', 'status');

        $recentTickets = Ticket::query()
            ->with(['department:id,name', 'category:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('staff.dashboard', [
            'openTicketCount' => Ticket::query()->whereIn('status', $openStatuses)->count(),
            'newTicketCount' => $statusCounts->get('New', 0),
            'unassignedTicketCount' => Ticket::query()
                ->whereIn('status', $openStatuses)
                ->whereNull('assigned_to_user_id')
                ->count(),
            'assignedToMeCount' => Ticket::query()
                ->whereIn('status', $openStatuses)
                ->where('assigned_to_user_id', $request->user()->id)
                ->count(),
            'statusCounts' => $statusCounts,
            'recentTickets' => $recentTickets,
        ]);
    }
}
