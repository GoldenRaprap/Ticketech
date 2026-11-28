@extends('layouts.staff')
@section('title', 'Client Reports and Moderation')
@section('heading', 'Client Moderation')
@section('content')
<div class="workspace-page admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / CLIENT SAFETY</span>
            <h1>Client moderation</h1>
            <p>Manage message-screening phrases, review staff reports and automatically flagged client messages, then manage warning or email-block decisions.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> Admin review required</span>
    </header>

    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->has('phrase') || $errors->has('reason') || $errors->has('is_active'))
        <div class="workspace-alert workspace-alert--error" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="admin-console-card">
        <div class="admin-console-card-heading">
            <div>
                <span class="admin-page-eyebrow">AUTOMATIC SCREENING</span>
                <h2>Message screening phrases</h2>
                <p>Active phrases are checked against new client tickets and requester replies. A match creates a report for admin review; it does not automatically warn or block the client.</p>
            </div>
            <span class="admin-readonly-badge">{{ $conductPhrases->where('is_active', true)->count() }} active</span>
        </div>
        <form class="moderation-phrase-add" action="{{ route('admin.moderation.phrases.store') }}" method="post">
            @csrf
            <label class="workspace-field">
                <span>New phrase</span>
                <input type="text" name="phrase" maxlength="150" value="{{ old('phrase') }}" placeholder="Enter a phrase to detect" required>
            </label>
            <label class="workspace-field">
                <span>Report category</span>
                <select name="reason" required>
                    <option value="">Select a category</option>
                    <option value="threatening" @selected(old('reason') === 'threatening')>Threatening</option>
                    <option value="abusive" @selected(old('reason') === 'abusive')>Abusive</option>
                </select>
            </label>
            <button class="workspace-button workspace-button--primary" type="submit">Add phrase</button>
        </form>
        <div class="admin-priority-rules-grid moderation-phrase-groups">
            @foreach (['threatening' => 'Threatening', 'abusive' => 'Abusive'] as $reason => $label)
                @php
                    $phrasesForReason = $conductPhrases->where('reason', $reason);
                    $activePhraseCount = $phrasesForReason->where('is_active', true)->count();
                @endphp
                <div class="admin-priority-rule-field">
                    <span class="admin-priority-rule-label">{{ $label }} phrases</span>
                    <details
                        class="admin-priority-keyword-details"
                        @if ($errors->has('phrase') || $errors->has('reason'))
                            @if (old('reason') === $reason) open @endif
                        @endif
                    >
                        <summary>
                            <span>Manage phrases</span>
                            <span class="admin-priority-keyword-count">{{ $phrasesForReason->count() }} phrases · {{ $activePhraseCount }} active</span>
                        </summary>
                        <div class="admin-priority-keyword-content">
                            <ul class="moderation-phrase-list" aria-label="{{ $label }} screening phrases">
                                @foreach ($phrasesForReason as $phrase)
                                    <li class="moderation-phrase-item {{ $phrase->is_active ? '' : 'moderation-phrase-item--disabled' }}">
                                        <form class="moderation-phrase-edit" action="{{ route('admin.moderation.phrases.update', $phrase) }}" method="post">
                                            @csrf
                                            @method('put')
                                            <label class="visually-hidden" for="conduct-phrase-{{ $phrase->id }}">Phrase</label>
                                            <input id="conduct-phrase-{{ $phrase->id }}" type="text" name="phrase" maxlength="150" value="{{ $phrase->phrase }}" required>
                                            <input type="hidden" name="reason" value="{{ $reason }}">
                                            <button class="admin-console-button" type="submit">Save</button>
                                        </form>
                                        <form class="moderation-phrase-toggle" action="{{ route('admin.moderation.phrases.activation', $phrase) }}" method="post">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="is_active" value="{{ $phrase->is_active ? '0' : '1' }}">
                                            <button
                                                class="admin-priority-keyword-remove"
                                                type="submit"
                                                aria-label="{{ $phrase->is_active ? 'Disable' : 'Enable' }} screening phrase {{ $phrase->phrase }}"
                                                title="{{ $phrase->is_active ? 'Disable phrase' : 'Enable phrase' }}"
                                            >{{ $phrase->is_active ? '×' : '+' }}</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>
                </div>
            @endforeach
        </div>
    </section>

    <section class="admin-metric-grid" aria-label="Client moderation summary">
        <article class="admin-metric-card"><span class="admin-metric-icon admin-metric-icon--amber" aria-hidden="true">⚑</span><div><span>Pending reports</span><strong>{{ $pendingReportCount }}</strong></div><small>Awaiting administrator review</small></article>
        <article class="admin-metric-card"><span class="admin-metric-icon admin-metric-icon--blue" aria-hidden="true">!</span><div><span>Active warnings</span><strong>{{ $activeWarningCount }}</strong></div><small>Client emails with a warning</small></article>
        <article class="admin-metric-card"><span class="admin-metric-icon admin-metric-icon--red" aria-hidden="true">⊘</span><div><span>Blocked emails</span><strong>{{ $activeBlockCount }}</strong></div><small>Blocked from new submissions</small></article>
    </section>

    <section class="admin-console-card">
        <div class="admin-console-card-heading">
            <div><span class="admin-page-eyebrow">REPORT QUEUE</span><h2>Conduct reports</h2><p>Review the evidence before applying an action. Automated flags do not automatically restrict a client.</p></div>
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
                        @if ($report->reporter_id === null)
                            <span class="status-pill status-pill--on-hold">Automated flag</span>
                        @endif
                    </div>
                    <dl class="moderation-report-meta">
                        <div><dt>Reason</dt><dd>{{ str($report->reason)->replace('_', ' ')->title() }}</dd></div>
                        <div><dt>Reported by</dt><dd>{{ $report->reporter?->name ?? 'Automated screening' }}</dd></div>
                        <div><dt>Submitted</dt><dd>{{ $report->created_at->copy()->timezone(config('app.display_timezone'))->format('M j, Y g:i A') }}</dd></div>
                        @if ($report->action)
                            <div><dt>Decision</dt><dd>{{ ucfirst($report->action) }}{{ $report->reviewedBy ? ' by '.$report->reviewedBy->name : '' }}</dd></div>
                        @endif
                    </dl>
                    @if ($report->details)
                        <p class="moderation-report-details">{{ $report->details }}</p>
                    @endif
                    <div class="moderation-actions">
                        <a class="workspace-button workspace-button--secondary" href="{{ route('staff.ticket-detail', $report->ticket) }}">View ticket</a>
                        @if ($report->status === 'pending')
                            @foreach (['warn' => 'Issue warning email', 'block' => 'Block email', 'dismiss' => 'Dismiss report'] as $action => $label)
                                <form action="{{ route('admin.moderation.review', $report) }}" method="post">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="action" value="{{ $action }}">
                                    <button class="workspace-button {{ $action === 'block' ? 'workspace-button--danger' : 'workspace-button--secondary' }}" type="submit">{{ $label }}</button>
                                </form>
                            @endforeach
                        @endif
                    </div>
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
                        <small>{{ $moderation->actedBy?->name ?? 'Administrator' }} · Updated {{ $moderation->updated_at->copy()->timezone(config('app.display_timezone'))->format('M j, Y') }}</small>
                    </div>
                    @if (! $moderation->cleared_at)
                        <form action="{{ route('admin.moderation.clear', $moderation) }}" method="post">
                            @csrf
                            @method('patch')
                            <button class="workspace-button workspace-button--secondary" type="submit">Clear action</button>
                        </form>
                    @else
                        <small>Cleared {{ $moderation->cleared_at->copy()->timezone(config('app.display_timezone'))->format('M j, Y') }}</small>
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
