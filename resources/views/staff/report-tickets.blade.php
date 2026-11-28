@extends('layouts.staff')
@section('title', $insightTitle)
@section('heading', 'Staff Workspace')
@section('content')
<div class="workspace-page reports-page">
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Report drilldown</span>
            <h1>{{ $insightTitle }}</h1>
            <p>{{ $selectedDepartmentName }} · {{ $periodRangeLabel }} · {{ $tickets->total() }} matching {{ $tickets->total() === 1 ? 'ticket' : 'tickets' }}</p>
        </div>
        <a class="workspace-button workspace-button--secondary" href="{{ route('staff.reports', $reportFilters) }}"><span aria-hidden="true">←</span> Back to report</a>
    </header>

    <section class="workspace-card report-chart-card" aria-label="Tickets matching report insight">
        <div class="ticket-table-scroll">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th scope="col">Ticket</th>
                        <th scope="col">Department</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Assignee</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td>
                                <a class="ticket-number-link" href="{{ route('staff.ticket-detail', $ticket) }}">
                                    <strong>{{ $ticket->ticket_number }}</strong>
                                    <span>{{ $ticket->subject }}</span>
                                </a>
                            </td>
                            <td>{{ $ticket->department?->name ?? 'Unassigned' }}</td>
                            <td><span class="priority-pill priority-pill--{{ strtolower($ticket->priority) }}">{{ $ticket->priority }}</span></td>
                            <td>{{ $ticket->assignedTo?->name ?? 'Unassigned' }}</td>
                            <td><span class="status-pill status-pill--{{ strtolower(str_replace(' ', '-', $ticket->status)) }}">{{ $ticket->status }}</span></td>
                            <td>{{ $ticket->created_at->copy()->timezone(config('app.display_timezone'))->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="workspace-empty-state workspace-empty-state--compact">
                                    <strong>No tickets match this insight</strong>
                                    <p>The report count may have changed since it was generated.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="workspace-pagination">{{ $tickets->links() }}</div>
        @endif
    </section>
</div>
@endsection
