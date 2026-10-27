@extends('layouts.staff')
@section('title', 'Audit Logs')
@section('heading', 'Audit Logs')
@section('content')
<div class="admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / ACCOUNTABILITY</span>
            <h1>Audit logs</h1>
            <p>Review staff and administrator sign-ins, page access, account changes, and ticket or system actions.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> Recorded activity</span>
    </header>

    <section class="admin-console-card admin-audit-card">
        <header class="admin-console-card-heading">
            <div><span class="admin-page-eyebrow">ACTIVITY REGISTER</span><h2>System events</h2><p>Filter recorded events by date, user, route, target, or network address.</p></div>
            <span class="admin-readonly-badge">{{ $events->total() }} matching events</span>
        </header>

        <form class="admin-console-toolbar" action="{{ route('admin.audit-logs') }}" method="get" data-auto-submit-on-change>
            <label class="admin-search-field">
                <span class="visually-hidden">Search activity</span>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.7"/><path d="m15.5 15.5 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search activity, target, or route" aria-label="Search activity">
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
                        <option value="{{ $actor->id }}" @selected((string) ($filters['actor'] ?? '') === (string) $actor->id)>{{ $actor->displayName() }}</option>
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
                <thead><tr><th>Date and time</th><th>Actor</th><th>Event</th><th>Target</th><th>Request</th><th>IP address</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y') }}<small>{{ $event->created_at->format('g:i A') }}</small></time></td>
                            @php($actorName = $event->actor?->name ?? $event->actor_name ?? 'Requester')
                            <td><span class="admin-audit-actor"><span>{{ strtoupper(substr($actorName, 0, 1)) }}</span>{{ $actorName }}</span></td>
                            <td><span class="admin-audit-event">{{ \Illuminate\Support\Str::headline($event->event) }}</span></td>
                            <td>
                                @if ($event->ticket)
                                    <a class="admin-audit-ticket" href="{{ route('staff.ticket-detail', $event->ticket) }}">{{ $event->ticket->ticket_number }}</a>
                                    <small>{{ $event->ticket->subject }}</small>
                                @elseif ($event->subject_name)
                                    <span>{{ $event->subject_name }}</span>
                                    <small>{{ $event->subject_type }}{{ $event->subject_id ? ' #'.$event->subject_id : '' }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <span>{{ $event->request_method ?? '—' }}</span>
                                <small>{{ $event->route_name ?? 'Ticket event' }}</small>
                            </td>
                            <td>
                                <span>{{ $event->ip_address ?? '—' }}</span>
                                @if ($event->user_agent)
                                    <small title="{{ $event->user_agent }}">Browser details available</small>
                                @endif
                            </td>
                            <td class="admin-audit-description">
                                {{ $event->description }}
                                @if (! empty($event->properties['before']) || ! empty($event->properties['after']))
                                    <small>
                                        @foreach (($event->properties['before'] ?? []) as $field => $value)
                                            {{ \Illuminate\Support\Str::headline($field) }}: {{ $value }} →
                                        @endforeach
                                        @foreach (($event->properties['after'] ?? []) as $field => $value)
                                            {{ $value }}@if (! $loop->last) · @endif
                                        @endforeach
                                    </small>
                                @elseif (isset($event->properties['outcome']))
                                    <small>Outcome: {{ \Illuminate\Support\Str::headline($event->properties['outcome']) }}{{ isset($event->properties['response_status']) ? ' · HTTP '.$event->properties['response_status'] : '' }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="admin-audit-empty"><span aria-hidden="true">⌕</span><strong>No matching activity</strong><small>Try another search term or clear the filters.</small></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="admin-console-card-footer"><span>Showing {{ $events->count() }} of {{ $events->total() }} events</span>{{ $events->links() }}</footer>
    </section>
</div>
@endsection
