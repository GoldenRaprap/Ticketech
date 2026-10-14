@extends('layouts.staff')
@section('title', 'Dashboard')
@section('content')
<div class="dashboard-shell">
    <section class="stat-grid stat-grid--dashboard">
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Ongoing Tickets</span><span class="mini-indicator">↗</span></div>
            <strong>{{ $openTicketCount }}</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>New</span><span class="mini-indicator">↗</span></div>
            <strong>{{ $newTicketCount }}</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Unassigned</span><span class="mini-indicator">↗</span></div>
            <strong>{{ $unassignedTicketCount }}</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Assigned to Me</span><span class="mini-indicator">↗</span></div>
            <strong>{{ $assignedToMeCount }}</strong>
        </article>
    </section>

    <section class="dashboard-columns">
        <div class="panel panel--wide">
            <div class="panel-header-row">
                <h3>Recent Tickets</h3>
                <a href="{{ route('staff.tickets') }}">View All Requests</a>
            </div>

            <div class="ticket-list" role="list">
                @forelse ($recentTickets as $ticket)
                    <div class="ticket-row" role="listitem">
                        <div class="ticket-person">
                            <span class="ticket-avatar">{{ strtoupper(substr($ticket->requester_name, 0, 1)) }}</span>
                            <div>
                                <strong>{{ $ticket->requester_name }}</strong>
                                <small>{{ $ticket->requester_email }}</small>
                            </div>
                        </div>
                        <div class="ticket-id">{{ $ticket->ticket_number }}</div>
                        <div class="ticket-subject">{{ $ticket->subject }}</div>
                        <div class="ticket-tag {{ strtolower($ticket->priority) === 'high' ? 'tag-priority' : 'tag-low' }}">{{ $ticket->priority }}</div>
                        <div class="ticket-date">{{ $ticket->created_at->format('M j, Y') }}</div>
                    </div>
                @empty
                    <p class="empty-note">No tickets have been submitted yet.</p>
                @endforelse
            </div>
        </div>

        <aside class="panel panel--side">
            <div class="panel-header-row">
                <h3>Ticket Status</h3>
            </div>
            @forelse ($statusCounts as $status => $count)
                <div class="person-row"><span>{{ $status }}</span><strong>{{ $count }}</strong></div>
            @empty
                <p class="empty-note">No status data yet.</p>
            @endforelse
        </aside>
    </section>
</div>
@endsection