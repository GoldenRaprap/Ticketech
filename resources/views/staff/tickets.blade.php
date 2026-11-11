@extends('layouts.staff')
@section('title', 'Tickets')
@section('content')
<div class="workspace-page tickets-page">
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Helpdesk operations</span>
            <h1>Ticket queue</h1>
            <p>{{ $departmentName ? "Queue for {$departmentName}. " : '' }}Review incoming requests, follow progress, and keep work moving.</p>
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
            <div>
                <h2>{{ $departmentName ? "{$departmentName} tickets" : 'All tickets' }}</h2>
                <p>Search requests or filter by status, priority, and assignee.</p>
                @if (auth()->user()->isDepartmentAdmin())
                    <p>Review and manage tickets across all departments, including unassigned requests.</p>
                @elseif (! auth()->user()->isAdmin())
                    <p>Enter an exact ticket ID to view another department’s ticket in read-only mode.</p>
                @endif
            </div>
        </div>
        <form class="ticket-filters" action="{{ route('staff.tickets') }}" method="get" data-auto-submit-on-change>
            <label class="ticket-search-field"><span class="sr-only">Search tickets</span><span aria-hidden="true">⌕</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by ticket ID, subject, or requester" aria-label="Search tickets"></label>
            @if ($canFilterByDepartment)
                <label><span class="sr-only">Filter by department</span><select name="department_id" aria-label="Filter by department">
                    @if (auth()->user()->isAdmin() || auth()->user()->isDepartmentAdmin())
                        <option value="">Department: All</option>
                    @endif
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select></label>
            @endif
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
            <label><span class="sr-only">Filter by assignee</span><select name="assignee" aria-label="Filter by assignee" @disabled($selectedDepartmentId === null && ! $canFilterByDepartment)>
                <option value="">{{ $selectedDepartmentId === null && ! $canFilterByDepartment ? 'Select department first' : 'Assignee: All' }}</option>
                <option value="unassigned" @selected(($filters['assignee'] ?? '') === 'unassigned') @disabled($selectedDepartmentId === null && ! $canFilterByDepartment)>Unassigned</option>
                @foreach ($assignees as $assignee)
                    <option value="{{ $assignee->id }}" @selected((string) ($filters['assignee'] ?? '') === (string) $assignee->id)>{{ $assignee->displayName() }}{{ $assignee->isDepartmentAdmin() ? ' (Department admin)' : '' }}</option>
                @endforeach
            </select></label>
            @if (count($filters) > 0)
                <a class="workspace-button workspace-button--secondary" href="{{ route('staff.tickets') }}">Clear</a>
            @endif
        </form>
        <div class="ticket-table-scroll">
            <table class="workspace-table">
                <thead><tr><th scope="col">Ticket</th><th scope="col">Department</th><th scope="col">Requester</th><th scope="col">Priority</th><th scope="col">Assignee</th><th scope="col">Status</th><th scope="col">Resolution target</th><th scope="col">Created</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr data-ticket-row data-ticket-url="{{ route('staff.ticket-detail', $ticket) }}" tabindex="0" aria-label="Open ticket {{ $ticket->ticket_number }}: {{ $ticket->subject }}">
                            <td>
                                <a class="ticket-number-link" href="{{ route('staff.ticket-detail', $ticket) }}">
                                    <strong>{{ $ticket->ticket_number }}</strong>
                                    <span>{{ $ticket->subject }}</span>
                                    @if (! $ticket->isVisibleTo(auth()->user()))
                                        <span class="ticket-cross-department-label">View only · {{ $ticket->department?->name ?? 'Other department' }}</span>
                                    @endif
                                </a>
                            </td>
                            <td>{{ $ticket->department?->name ?? 'Unassigned' }}</td>
                            <td><div class="table-requester"><span class="requester-avatar requester-avatar--small" aria-hidden="true">{{ strtoupper(substr($ticket->requester_name, 0, 1)) }}</span><strong>{{ $ticket->requester_name }}</strong></div></td>
                            <td><span class="priority-pill priority-pill--{{ strtolower($ticket->priority) }}">{{ $ticket->priority }}</span></td>
                            <td>{{ $ticket->assignedTo?->name ?? 'Unassigned' }}</td>
                            <td><span class="status-pill status-pill--{{ strtolower(str_replace(' ', '-', $ticket->status)) }}">{{ $ticket->status }}</span></td>
                            <td>
                                @if (in_array($ticket->status, ['Resolved', 'Closed'], true))
                                    <span>Completed</span>
                                @elseif (in_array($ticket->status, ['Waiting for User', 'On Hold'], true))
                                    <span>Paused</span>
                                @elseif ($ticket->resolution_due_at && $ticket->resolution_due_at->lessThanOrEqualTo(now()))
                                    <span class="priority-pill priority-pill--urgent">Overdue</span>
                                @elseif ($ticket->resolution_warning_at && $ticket->resolution_warning_at->lessThanOrEqualTo(now()))
                                    <span class="priority-pill priority-pill--high">Due soon</span>
                                @elseif ($ticket->resolution_due_at)
                                    <time datetime="{{ $ticket->resolution_due_at->copy()->timezone(config('app.display_timezone'))->toIso8601String() }}">{{ $ticket->resolution_due_at->copy()->timezone(config('app.display_timezone'))->format('M j, g:i A') }}</time>
                                @else
                                    <span>Not calculated</span>
                                @endif
                            </td>
                            <td><time datetime="{{ $ticket->created_at->copy()->timezone(config('app.display_timezone'))->toDateString() }}">{{ $ticket->created_at->copy()->timezone(config('app.display_timezone'))->format('M j, Y') }}</time></td>
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
