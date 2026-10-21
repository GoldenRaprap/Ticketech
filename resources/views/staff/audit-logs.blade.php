@extends('layouts.staff')
@section('title', 'Audit Logs')
@section('heading', 'Audit Logs')
@section('content')
<div class="admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / ACCOUNTABILITY</span>
            <h1>Audit logs</h1>
            <p>Review recorded ticket submissions, replies, assignments, status changes, and moderation actions.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> Recorded activity</span>
    </header>

    <section class="admin-console-card admin-audit-card">
        <header class="admin-console-card-heading">
            <div><span class="admin-page-eyebrow">ACTIVITY REGISTER</span><h2>System events</h2><p>Filter events by date, user, or ticket details.</p></div>
            <span class="admin-readonly-badge">{{ $events->total() }} matching events</span>
        </header>

        <form class="admin-console-toolbar" action="{{ route('admin.audit-logs') }}" method="get">
            <label class="admin-search-field">
                <span class="visually-hidden">Search activity</span>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.7"/><path d="m15.5 15.5 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search activity or ticket" aria-label="Search activity">
            </label>
            <label class="admin-filter-field">
                <span class="visually-hidden">Filter by date</span>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" aria-label="Filter by date">
            </label>
            <label class="admin-filter-field">
                <span class="visually-hidden">Filter by user</span>
                <select name="actor" aria-label="Filter by user">
                    <option value="">All users</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string) ($filters['actor'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="admin-console-button" type="submit">Apply filters</button>
            @if ($filters !== [])
                <a class="admin-console-clear" href="{{ route('admin.audit-logs') }}">Clear</a>
            @endif
        </form>

        <div class="admin-table-wrap">
            <table class="admin-console-table admin-audit-table">
                <thead><tr><th>Date and time</th><th>Actor</th><th>Event</th><th>Ticket</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y') }}<small>{{ $event->created_at->format('g:i A') }}</small></time></td>
                            <td><span class="admin-audit-actor"><span>{{ strtoupper(substr($event->actor?->name ?? 'RQ', 0, 1)) }}</span>{{ $event->actor?->name ?? 'Requester' }}</span></td>
                            <td><span class="admin-audit-event">{{ \Illuminate\Support\Str::headline($event->event) }}</span></td>
                            <td>
                                @if ($event->ticket)
                                    <a class="admin-audit-ticket" href="{{ route('staff.ticket-detail', $event->ticket) }}">{{ $event->ticket->ticket_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="admin-audit-description">{{ $event->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="admin-audit-empty"><span aria-hidden="true">⌕</span><strong>No matching activity</strong><small>Try another search term or clear the filters.</small></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="admin-console-card-footer"><span>Showing {{ $events->count() }} of {{ $events->total() }} events</span>{{ $events->links() }}</footer>
    </section>
</div>
@endsection
