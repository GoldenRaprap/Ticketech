<?php

namespace App\Http\Controllers;

use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'actor' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $events = TicketEvent::query()
            ->with(['actor:id,name', 'ticket:id,ticket_number,subject'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('event', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas('actor', fn ($actorQuery) => $actorQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('ticket', function ($ticketQuery) use ($search): void {
                            $ticketQuery->where('ticket_number', 'like', '%'.$search.'%')
                                ->orWhere('subject', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['date'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', $date))
            ->when($filters['actor'] ?? null, fn ($query, int $actorId) => $query->where('actor_id', $actorId))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('staff.audit-logs', [
            'events' => $events,
            'filters' => $filters,
            'actors' => User::query()
                ->whereIn('id', TicketEvent::query()->whereNotNull('actor_id')->select('actor_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
