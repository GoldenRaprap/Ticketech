@extends('layouts.staff')
@section('title', 'Dashboard')
@section('content')
<div class="dashboard-shell">
    <section class="stat-grid stat-grid--dashboard">
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Ongoing Tickets</span><span class="mini-indicator">↗</span></div>
            <strong>21</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Assigned to Me</span><span class="mini-indicator">↗</span></div>
            <strong>3</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Assigned to Others</span><span class="mini-indicator">↗</span></div>
            <strong>15</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Unassigned</span><span class="mini-indicator">↗</span></div>
            <strong>2</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Bookmarks</span><span class="mini-indicator">↗</span></div>
            <strong>0</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Due Soon</span><span class="mini-indicator">◌</span></div>
            <strong>3</strong>
        </article>
    </section>

    <section class="dashboard-columns">
        <div class="panel panel--wide">
            <div class="panel-header-row">
                <h3>Ongoing Tickets</h3>
                <a href="{{ route('staff.tickets') }}">View All Requests →</a>
            </div>

            <div class="ticket-list" role="list">
                @php
                    $tickets = [
                        ['Precincie Montero', 'precicje.montero@pcu.edu.ph', 'L1J-URL-9WXT', 'Inaccessible Site', 'Priority', 'Sep 1, 2026'],
                        ['Bay Genesis Husyo', 'bay.genesis.husyo@pcu.edu.ph', 'A72-OPCM-MIUP', 'Grades Request', 'Concern', 'Sep 1, 2026'],
                        ['Augustus Marteja', 'augustus.marteja@pcu.edu.ph', 'R10-MCHA-G1PP', 'Card Request', 'Low', 'Sep 1, 2026'],
                        ['Felix Cardenas', 'felix.cardenas@pcu.edu.ph', 'T87-JCAK-L6PA', 'Broken Payment', 'Priority', 'Sep 1, 2026'],
                    ];
                @endphp

                @foreach ($tickets as $ticket)
                    <div class="ticket-row" role="listitem">
                        <div class="ticket-person">
                            <span class="ticket-avatar">{{ strtoupper(substr($ticket[0], 0, 1)) }}</span>
                            <div>
                                <strong>{{ $ticket[0] }}</strong>
                                <small>{{ $ticket[1] }}</small>
                            </div>
                        </div>
                        <div class="ticket-id">{{ $ticket[2] }}</div>
                        <div class="ticket-subject">{{ $ticket[3] }}</div>
                        <div class="ticket-tag {{ strtolower($ticket[4]) === 'priority' ? 'tag-priority' : (strtolower($ticket[4]) === 'concern' ? 'tag-concern' : 'tag-low') }}">{{ $ticket[4] }}</div>
                        <div class="ticket-date">{{ $ticket[5] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="panel panel--side">
            <div class="panel-header-row">
                <h3>Report Summary</h3>
            </div>

            <div class="donut-wrap">
                <div class="donut-chart">
                    <div class="donut-inner">87%</div>
                </div>
            </div>

            <div class="legend">
                <div><span class="dot dot-blue"></span> Reserved <strong>86.7%</strong></div>
                <div><span class="dot dot-red"></span> Unresolved <strong>12.3%</strong></div>
            </div>
        </aside>
    </section>

    <section class="bottom-panels">
        <div class="panel panel--mock">
            <div class="panel-header-row">
                <h3>Show Tickets</h3>
                <a href="{{ route('staff.tickets') }}">Show Tickets →</a>
            </div>

            <div class="checkbox-row">
                <label><input type="checkbox" /> Select All</label>
                <label><input type="checkbox" /> New</label>
                <label><input type="checkbox" /> In Progress</label>
                <label><input type="checkbox" /> Resolved</label>
                <label><input type="checkbox" /> Waiting Reply</label>
                <label><input type="checkbox" /> On Hold</label>
            </div>

            <button class="dark-button" type="button">Show Tickets</button>
        </div>

        <div class="panel panel--list">
            <div class="panel-header-row">
                <h3>Find a Ticket</h3>
                <a href="{{ route('staff.tickets') }}">View All Requests →</a>
            </div>

            <div class="ticket-cards">
                <div class="mini-ticket-row">
                    <div class="mini-ticket-name">Sarah Martinez</div>
                    <div class="mini-ticket-email">sarah.martinez@pcu.edu.ph</div>
                    <div class="mini-ticket-status tag-low">Low</div>
                    <div class="mini-ticket-date">Sep 1, 2026</div>
                </div>
                <div class="mini-ticket-row">
                    <div class="mini-ticket-name">Roberto Paniban</div>
                    <div class="mini-ticket-email">roberto.paniban@pcu.edu.ph</div>
                    <div class="mini-ticket-status tag-priority">Priority</div>
                    <div class="mini-ticket-date">Sep 1, 2026</div>
                </div>
                <div class="mini-ticket-row">
                    <div class="mini-ticket-name">Johnny Gilbert</div>
                    <div class="mini-ticket-email">johnny.gilbert@pcu.edu.ph</div>
                    <div class="mini-ticket-status tag-priority">Priority</div>
                    <div class="mini-ticket-date">Sep 1, 2026</div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection