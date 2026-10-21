@extends('layouts.staff')
@section('title', 'Tickets')
@section('content')
<div class="workspace-page tickets-page">
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Helpdesk operations</span>
            <h1>Ticket queue</h1>
            <p>Review incoming requests, follow progress, and keep work moving.</p>
        </div>
        <span class="queue-count"><strong>{{ $ticketCount }}</strong> total tickets</span>
    </header>

    <section class="queue-summary" aria-label="Ticket queue summary">
        <div><span class="queue-summary-icon queue-summary-icon--blue" aria-hidden="true">▤</span><span><small>All tickets</small><strong>{{ $ticketCount }}</strong></span></div>
        <div><span class="queue-summary-icon queue-summary-icon--amber" aria-hidden="true">✦</span><span><small>New</small><strong>{{ $newCount }}</strong></span></div>
        <div><span class="queue-summary-icon queue-summary-icon--violet" aria-hidden="true">◷</span><span><small>In progress</small><strong>{{ $inProgressCount }}</strong></span></div>
        <div><span class="queue-summary-icon queue-summary-icon--green" aria-hidden="true">✓</span><span><small>Resolved / closed</small><strong>{{ $completedCount }}</strong></span></div>
    </section>

    <section class="workspace-card ticket-queue-card">
        <div class="queue-toolbar-heading">
            <div><h2>All tickets</h2><p>Search requests or filter by status, priority, and assignee.</p></div>
        </div>
        <form class="ticket-filters" action="{{ route('staff.tickets') }}" method="get">
            <label class="ticket-search-field"><span class="sr-only">Search tickets</span><span aria-hidden="true">⌕</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by ticket number, subject, or requester" aria-label="Search tickets"></label>
            <label><span class="sr-only">Filter by status</span><select name="status" aria-label="Filter by status">
                <option value="">Status: All</option>
                @foreach (['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold', 'Resolved', 'Closed'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select></label>
            <label><span class="sr-only">Filter by priority</span><select name="priority" aria-label="Filter by priority">
                <option value="">Priority: All</option>
                @foreach (['Urgent', 'High', 'Medium', 'Low'] as $priority)
                    <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ $priority }}</option>
                @endforeach
            </select></label>
            <label><span class="sr-only">Filter by staff</span><select name="assignee" aria-label="Filter by staff">
                <option value="">Assignee: All</option>
                <option value="unassigned" @selected(($filters['assignee'] ?? '') === 'unassigned')>Unassigned</option>
                @foreach ($assignees as $assignee)
                    <option value="{{ $assignee->id }}" @selected((string) ($filters['assignee'] ?? '') === (string) $assignee->id)>{{ $assignee->name }}</option>
                @endforeach
            </select></label>
            <button class="workspace-button workspace-button--primary" type="submit">Apply filters</button>
            @if (count($filters) > 0)
                <a class="workspace-button workspace-button--secondary" href="{{ route('staff.tickets') }}">Clear</a>
            @endif
        </form>
        <div class="ticket-table-scroll">
            <table class="workspace-table">
                <thead><tr><th scope="col">Ticket</th><th scope="col">Requester</th><th scope="col">Priority</th><th scope="col">Assignee</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><a class="ticket-number-link" href="{{ route('staff.ticket-detail', $ticket) }}"><strong>{{ $ticket->ticket_number }}</strong><span>{{ $ticket->subject }}</span></a></td>
                            <td><div class="table-requester"><span class="requester-avatar requester-avatar--small" aria-hidden="true">{{ strtoupper(substr($ticket->requester_name, 0, 1)) }}</span><strong>{{ $ticket->requester_name }}</strong></div></td>
                            <td><span class="priority-pill priority-pill--{{ strtolower($ticket->priority) }}">{{ $ticket->priority }}</span></td>
                            <td>{{ $ticket->assignedTo?->name ?? 'Unassigned' }}</td>
                            <td><span class="status-pill status-pill--{{ strtolower(str_replace(' ', '-', $ticket->status)) }}">{{ $ticket->status }}</span></td>
                            <td><time datetime="{{ $ticket->created_at->toDateString() }}">{{ $ticket->created_at->format('M j, Y') }}</time></td>
                            <td><a class="table-action-link" href="{{ route('staff.ticket-detail', $ticket) }}">Open <span aria-hidden="true">→</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="workspace-empty-state workspace-empty-state--compact"><strong>No matching tickets</strong><p>Try another search term or clear your filters.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="table-footer"><span>Showing <strong>{{ $tickets->count() }}</strong> of {{ $tickets->total() }} tickets</span>{{ $tickets->links() }}</footer>
    </section>
</div>
@endsection
