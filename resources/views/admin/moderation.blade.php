@extends('layouts.staff')
@section('title', 'Client Reports and Moderation')
@section('heading', 'Client Moderation')
@section('content')
<div class="workspace-page admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / CLIENT SAFETY</span>
            <h1>Client moderation</h1>
            <p>Review staff reports and manage warning or email-block decisions.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> Admin review required</span>
    </header>

    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif

    <section class="admin-metric-grid" aria-label="Client moderation summary">
        <article class="admin-metric-card"><span class="admin-metric-icon admin-metric-icon--amber" aria-hidden="true">⚑</span><div><span>Pending reports</span><strong>{{ $pendingReportCount }}</strong></div><small>Awaiting administrator review</small></article>
        <article class="admin-metric-card"><span class="admin-metric-icon admin-metric-icon--blue" aria-hidden="true">!</span><div><span>Active warnings</span><strong>{{ $activeWarningCount }}</strong></div><small>Client emails with a warning</small></article>
        <article class="admin-metric-card"><span class="admin-metric-icon admin-metric-icon--red" aria-hidden="true">⊘</span><div><span>Blocked emails</span><strong>{{ $activeBlockCount }}</strong></div><small>Blocked from new submissions</small></article>
    </section>

    <section class="admin-console-card">
        <div class="admin-console-card-heading">
            <div><span class="admin-page-eyebrow">STAFF SUBMISSIONS</span><h2>Conduct reports</h2><p>Review the evidence before applying an action. Reports do not automatically restrict a client.</p></div>
            <span class="admin-readonly-badge">{{ $reports->total() }} total</span>
        </div>
        @error('action')
            <div class="workspace-alert workspace-alert--error" role="alert">{{ $message }}</div>
        @enderror
        <div class="moderation-report-list">
            @forelse ($reports as $report)
                <article class="moderation-report admin-moderation-report">
                    <div class="moderation-report-heading">
                        <div>
                            <strong><a href="{{ route('staff.ticket-detail', $report->ticket) }}">{{ $report->ticket->ticket_number }} · {{ $report->ticket->subject }}</a></strong>
                            <p>{{ $report->ticket->requester_name }} · {{ $report->ticket->requester_email }}</p>
                        </div>
                        <span class="status-pill status-pill--{{ $report->status === 'pending' ? 'on-hold' : 'resolved' }}">{{ ucfirst($report->status) }}</span>
                    </div>
                    <dl class="moderation-report-meta">
                        <div><dt>Reason</dt><dd>{{ str($report->reason)->replace('_', ' ')->title() }}</dd></div>
                        <div><dt>Reported by</dt><dd>{{ $report->reporter->name }}</dd></div>
                        <div><dt>Submitted</dt><dd>{{ $report->created_at->format('M j, Y g:i A') }}</dd></div>
                        @if ($report->action)
                            <div><dt>Decision</dt><dd>{{ ucfirst($report->action) }}{{ $report->reviewedBy ? ' by '.$report->reviewedBy->name : '' }}</dd></div>
                        @endif
                    </dl>
                    @if ($report->details)
                        <p class="moderation-report-details">{{ $report->details }}</p>
                    @endif
                    @if ($report->status === 'pending')
                        <div class="moderation-actions">
                            @foreach (['warn' => 'Issue warning email', 'block' => 'Block email', 'dismiss' => 'Dismiss report'] as $action => $label)
                                <form action="{{ route('admin.moderation.review', $report) }}" method="post">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="action" value="{{ $action }}">
                                    <button class="workspace-button {{ $action === 'block' ? 'workspace-button--danger' : 'workspace-button--secondary' }}" type="submit">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <div class="workspace-empty-state"><strong>No client reports yet</strong><p>Staff-submitted reports will appear here for admin review.</p></div>
            @endforelse
        </div>
        {{ $reports->links() }}
    </section>

    <section class="admin-console-card">
        <div class="admin-console-card-heading">
            <div><span class="admin-page-eyebrow">CLIENT ACCESS DECISIONS</span><h2>Warnings and blocks</h2><p>Active and cleared actions remain visible here for accountability.</p></div>
        </div>
        <div class="moderation-client-list">
            @forelse ($moderations as $moderation)
                <article class="moderation-client">
                    <div>
                        <strong>{{ $moderation->email }}</strong>
                        <span class="status-pill status-pill--{{ $moderation->cleared_at ? 'resolved' : ($moderation->status === 'blocked' ? 'closed' : 'on-hold') }}">{{ $moderation->cleared_at ? 'Cleared' : ucfirst($moderation->status) }}</span>
                        <p>{{ $moderation->reason }}</p>
                        <small>{{ $moderation->actedBy?->name ?? 'Administrator' }} · Updated {{ $moderation->updated_at->format('M j, Y') }}</small>
                    </div>
                    @if (! $moderation->cleared_at)
                        <form action="{{ route('admin.moderation.clear', $moderation) }}" method="post">
                            @csrf
                            @method('patch')
                            <button class="workspace-button workspace-button--secondary" type="submit">Clear action</button>
                        </form>
                    @else
                        <small>Cleared {{ $moderation->cleared_at->format('M j, Y') }}</small>
                    @endif
                </article>
            @empty
                <div class="workspace-empty-state"><strong>No moderation actions</strong><p>Admin-issued warnings and email blocks will be listed here.</p></div>
            @endforelse
        </div>
        {{ $moderations->links() }}
    </section>
</div>
@endsection
