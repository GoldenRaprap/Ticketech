@extends('layouts.staff')
@section('title', 'Dashboard')
@section('heading', 'Staff Workspace')
@section('content')
<div class="workspace-page dashboard-page">
    <header class="workspace-page-heading">
        <div>
            <span class="workspace-eyebrow">Workspace overview</span>
            <h1>Dashboard</h1>
            <p>Welcome back, {{ auth()->user()->name }}. Here is the latest helpdesk activity.</p>
        </div>
        <a class="workspace-button workspace-button--primary" href="{{ route('staff.tickets') }}">Review ticket queue <span aria-hidden="true">→</span></a>
    </header>

    <section class="dashboard-metrics" aria-label="Ticket summary">
        <article class="metric-card metric-card--blue">
            <div class="metric-card-top"><span class="metric-icon" aria-hidden="true">◷</span><span class="metric-caption">Active workload</span></div>
            <strong>{{ $openTicketCount }}</strong>
            <span class="metric-label">Open tickets</span>
        </article>
        <article class="metric-card metric-card--amber">
            <div class="metric-card-top"><span class="metric-icon" aria-hidden="true">✦</span><span class="metric-caption">Needs attention</span></div>
            <strong>{{ $newTicketCount }}</strong>
            <span class="metric-label">New tickets</span>
        </article>
        <article class="metric-card metric-card--violet">
            <div class="metric-card-top"><span class="metric-icon" aria-hidden="true">○</span><span class="metric-caption">Queue health</span></div>
            <strong>{{ $unassignedTicketCount }}</strong>
            <span class="metric-label">Unassigned tickets</span>
        </article>
        <article class="metric-card metric-card--green">
            <div class="metric-card-top"><span class="metric-icon" aria-hidden="true">✓</span><span class="metric-caption">Your workload</span></div>
            <strong>{{ $assignedToMeCount }}</strong>
            <span class="metric-label">Assigned to me</span>
        </article>
    </section>

    <div class="dashboard-content-grid">
        <section class="workspace-card dashboard-recent-card">
            <div class="workspace-card-heading">
                <div><span class="workspace-eyebrow">Latest requests</span><h2>Recent tickets</h2></div>
                <a class="workspace-text-link" href="{{ route('staff.tickets') }}">View all tickets <span aria-hidden="true">→</span></a>
            </div>

            @forelse ($recentTickets as $ticket)
                <article class="dashboard-ticket-row">
                    <span class="requester-avatar" aria-hidden="true">{{ strtoupper(substr($ticket->requester_name, 0, 1)) }}</span>
                    <div class="dashboard-ticket-requester">
                        <strong>{{ $ticket->requester_name }}</strong>
                        <span>{{ $ticket->requester_email }}</span>
                    </div>
                    <div class="dashboard-ticket-subject">
                        <strong><a href="{{ route('staff.ticket-detail', $ticket) }}">{{ $ticket->subject }}</a></strong>
                        <span>{{ $ticket->ticket_number }}</span>
                    </div>
                    <span class="priority-pill priority-pill--{{ strtolower($ticket->priority) }}">{{ $ticket->priority }}</span>
                    <span class="status-pill">{{ $ticket->status }}</span>
                    <time datetime="{{ $ticket->created_at->toDateString() }}">{{ $ticket->created_at->format('M j, Y') }}</time>
                </article>
            @empty
                <div class="workspace-empty-state">
                    <span class="workspace-empty-icon" aria-hidden="true">▤</span>
                    <strong>No tickets yet</strong>
                    <p>New helpdesk requests will appear here.</p>
                </div>
            @endforelse
        </section>

        <aside class="workspace-card dashboard-status-card">
            <div class="workspace-card-heading">
                <div><span class="workspace-eyebrow">Queue breakdown</span><h2>Ticket status</h2></div>
            </div>
            <div class="status-summary">
                @forelse ($statusCounts as $status => $count)
                    <div class="status-summary-row">
                        <span class="status-summary-dot" aria-hidden="true"></span>
                        <span>{{ $status }}</span>
                        <strong>{{ $count }}</strong>
                    </div>
                @empty
                    <div class="workspace-empty-state workspace-empty-state--compact">
                        <p>Status data will appear when tickets are submitted.</p>
                    </div>
                @endforelse
            </div>
            <a class="workspace-button workspace-button--secondary workspace-button--full" href="{{ route('staff.tickets') }}">Open ticket queue</a>
        </aside>
    </div>
</div>
@endsection
