@extends('layouts.staff')
@section('title', 'Admin Console')
@section('heading', 'Admin Console')
@section('content')
<div class="admin-dashboard">
    <header class="admin-intro">
        <div>
            <p class="admin-eyebrow">SYSTEM ADMINISTRATION</p>
            <h1>Overview</h1>
        </div>
        <span class="admin-status"><span></span> System overview</span>
    </header>

    <section class="admin-stat-grid" aria-label="System totals">
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>All Tickets</span><span class="mini-indicator">▤</span></div>
            <strong>{{ $ticketCount }}</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Open Tickets</span><span class="mini-indicator">◷</span></div>
            <strong>{{ $openTicketCount }}</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Helpdesk Staff</span><span class="mini-indicator">◌</span></div>
            <strong>{{ $staffCount }}</strong>
        </article>
        <article class="stat-card stat-card--dashboard">
            <div class="stat-head"><span>Requesters</span><span class="mini-indicator">◎</span></div>
            <strong>{{ $requesterCount }}</strong>
        </article>
    </section>

    <div class="admin-management-layout">
        <section class="panel admin-controls-panel">
            <div class="panel-header-row">
                <div>
                    <p class="admin-eyebrow">ACCESS AND CONFIGURATION</p>
                    <h2>Administration</h2>
                </div>
            </div>

            <div class="admin-control-grid">
                <a class="admin-control-link" href="{{ route('admin.users') }}">
                    <span class="admin-control-icon">◌</span>
                    <span><strong>User access</strong><small>Manage staff and requester accounts</small></span>
                    <span class="admin-control-arrow">→</span>
                </a>
                <a class="admin-control-link" href="{{ route('admin.moderation') }}">
                    <span class="admin-control-icon">⚑</span>
                    <span><strong>Client moderation</strong><small>Review reports, warnings, and blocked emails</small></span>
                    <span class="admin-control-arrow">→</span>
                </a>
                <a class="admin-control-link" href="{{ route('admin.sla-settings') }}">
                    <span class="admin-control-icon">◷</span>
                    <span><strong>SLA settings</strong><small>Review response targets</small></span>
                    <span class="admin-control-arrow">→</span>
                </a>
                <a class="admin-control-link" href="{{ route('admin.audit-logs') }}">
                    <span class="admin-control-icon">▦</span>
                    <span><strong>Audit logs</strong><small>Review administrative activity</small></span>
                    <span class="admin-control-arrow">→</span>
                </a>
                <a class="admin-control-link" href="{{ route('admin.system-settings') }}">
                    <span class="admin-control-icon">⚙</span>
                    <span><strong>System settings</strong><small>Configure the helpdesk</small></span>
                    <span class="admin-control-arrow">→</span>
                </a>
            </div>
        </section>

        <aside class="panel admin-operations-panel">
            <div class="panel-header-row"><h2>Operations</h2></div>
            <p>Ticket handling remains available in the staff workspace.</p>
            <a class="admin-operations-link" href="{{ route('staff.tickets') }}">Open ticket queue <span>→</span></a>
            <a class="admin-operations-link" href="{{ route('staff.reports') }}">View service reports <span>→</span></a>
        </aside>
    </div>
</div>
@endsection
