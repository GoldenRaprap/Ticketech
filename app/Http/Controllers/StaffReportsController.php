<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffReportsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:7d,30d,semester,year,all'],
        ]);

        $period = $filters['period'] ?? '30d';
        $periodStart = match ($period) {
            '7d' => now()->subDays(6)->startOfDay(),
            'semester' => now()->subMonths(6)->startOfDay(),
            'year' => now()->subYear()->startOfDay(),
            'all' => null,
            default => now()->subDays(29)->startOfDay(),
        };

        $baseQuery = Ticket::query()->when(
            $periodStart,
            fn ($query) => $query->where('created_at', '>=', $periodStart),
        );
        $resolvedTickets = (clone $baseQuery)
            ->whereIn('status', ['Resolved', 'Closed'])
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);
        $averageResolutionHours = $resolvedTickets->isEmpty()
            ? null
            : round($resolvedTickets->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at) / 60), 1);

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('total', 'status');
        $priorityCounts = (clone $baseQuery)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->orderBy('priority')
            ->pluck('total', 'priority');

        return view('staff.reports', [
            'period' => $period,
            'totalTicketCount' => (clone $baseQuery)->count(),
            'openTicketCount' => (clone $baseQuery)->whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'resolvedTicketCount' => (clone $baseQuery)->whereIn('status', ['Resolved', 'Closed'])->count(),
            'averageResolutionHours' => $averageResolutionHours,
            'priorityCounts' => $priorityCounts,
            'statusCounts' => $statusCounts,
        ]);
    }
}
