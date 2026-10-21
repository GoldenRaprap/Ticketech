@extends('layouts.staff')
@section('title', $ticket->ticket_number)
@section('content')
<div class="workspace-page ticket-detail-page">
    <nav class="ticket-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('staff.tickets') }}">Tickets</a><span aria-hidden="true">/</span><span aria-current="page">{{ $ticket->ticket_number }}</span>
    </nav>

    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif

    <header class="ticket-detail-heading">
        <div class="ticket-detail-title">
            <div class="ticket-heading-meta">
                <span class="ticket-number-tag">{{ $ticket->ticket_number }}</span>
                <span class="status-pill status-pill--{{ strtolower(str_replace(' ', '-', $ticket->status)) }}">{{ $ticket->status }}</span>
                <span class="priority-pill priority-pill--{{ strtolower($ticket->priority) }}">{{ $ticket->priority }} priority</span>
            </div>
            <h1>{{ $ticket->subject }}</h1>
            <p><span class="requester-avatar requester-avatar--small" aria-hidden="true">{{ strtoupper(substr($ticket->requester_name, 0, 1)) }}</span> Submitted by <strong>{{ $ticket->requester_name }}</strong><span class="detail-separator">·</span><time datetime="{{ $ticket->created_at->toIso8601String() }}">{{ $ticket->created_at->format('F j, Y \a\t g:i A') }}</time></p>
        </div>
        <a class="workspace-button workspace-button--secondary" href="{{ route('staff.tickets') }}"><span aria-hidden="true">←</span> Back to tickets</a>
    </header>

    <div class="ticket-detail-layout">
        <div class="ticket-detail-main">
            <section class="workspace-card ticket-description-card">
                <div class="workspace-card-heading"><div><span class="workspace-eyebrow">Request details</span><h2>Problem description</h2></div><span class="ticket-updated-label">Submitted {{ $ticket->created_at->format('M j') }}</span></div>
                <div class="ticket-description-content"><p>{{ $ticket->description }}</p></div>
                <div class="ticket-detail-tags">
                    <span class="detail-tag"><small>Requester email</small><strong>{{ $ticket->requester_email }}</strong></span>
                    <span class="detail-tag"><small>Student or Employee ID</small><strong>{{ $ticket->student_id }}</strong></span>
                    @if ($ticket->requester_phone)
                        <span class="detail-tag"><small>Phone number</small><strong>{{ $ticket->requester_phone }}</strong></span>
                    @endif
                </div>
            </section>

            <section class="workspace-card ticket-attachments-card">
                <div class="workspace-card-heading"><div><span class="workspace-eyebrow">Supporting files</span><h2>Attachments <span class="section-count">{{ $ticket->attachments->count() }}</span></h2></div></div>
                @forelse ($ticket->attachments as $attachment)
                    <a class="ticket-attachment-link" href="{{ route('staff.ticket-attachments.download', [$ticket, $attachment]) }}">
                        <span class="ticket-attachment-icon" aria-hidden="true">▧</span>
                        <span><strong>{{ $attachment->original_name }}</strong><small>{{ $attachment->size_bytes ? number_format($attachment->size_bytes / 1024, 0).' KB' : 'Attachment' }}</small></span>
                        <span class="ticket-attachment-download">Download <span aria-hidden="true">↓</span></span>
                    </a>
                @empty
                    <div class="ticket-no-attachments"><span aria-hidden="true">⌁</span><span>No files were attached to this ticket.</span></div>
                @endforelse
            </section>

            <section class="workspace-card conversation-card">
                <div class="workspace-card-heading conversation-card-heading">
                    <div>
                        <span class="workspace-eyebrow">Ticket activity</span>
                        <h2>Conversation <span class="section-count">{{ $ticket->comments->count() }}</span></h2>
                        <p class="conversation-card-description">Replies are shared with the requester. Internal notes stay within the helpdesk.</p>
                    </div>
                    <span class="conversation-visibility-note"><span aria-hidden="true">●</span> Internal notes are staff-only</span>
                </div>
                <div class="workspace-alert workspace-alert--success conversation-new-reply" data-new-reply-notice role="status" hidden>
                    <span>A new reply has been added. Refresh this ticket to view the latest conversation.</span>
                    <button class="workspace-button workspace-button--secondary" type="button" data-refresh-ticket>Refresh ticket</button>
                </div>
                <div
                    class="conversation-list"
                    data-ticket-comment-check
                    data-updates-url="{{ route('staff.ticket-updates', $ticket) }}"
                    data-last-comment-id="{{ $ticket->comments->max('id') ?? 0 }}"
                >
                    @forelse ($ticket->comments as $comment)
                        <article class="conversation-entry {{ $comment->is_internal ? 'conversation-entry--internal' : ($comment->author?->isStaff() ? 'conversation-entry--staff' : 'conversation-entry--requester') }}">
                            <span class="conversation-avatar {{ $comment->author?->isStaff() ? 'conversation-avatar--staff' : '' }}" aria-hidden="true">{{ strtoupper(substr($comment->author_name, 0, 2)) }}</span>
                            <div class="conversation-entry-body">
                                <div class="conversation-entry-head">
                                    <strong>{{ $comment->author_name }}</strong>
                                    <span class="conversation-role">{{ $comment->is_internal ? 'Internal note' : ($comment->author?->isStaff() ? 'Public reply' : 'Requester reply') }}</span>
                                    <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M j, Y g:i A') }}</time>
                                </div>
                                <p>{{ $comment->body }}</p>
                            </div>
                        </article>
                    @empty
                        <div class="workspace-empty-state workspace-empty-state--compact"><strong>No replies yet</strong><p>Add a reply or internal note to record progress.</p></div>
                    @endforelse
                </div>
                <form class="reply-composer" action="{{ route('staff.ticket-comments.store', $ticket) }}" method="post">
                    @csrf
                    <div class="conversation-composer-intro">
                        <strong>Reply to requester</strong>
                        <span>Use the checkbox to keep a note visible only to staff.</span>
                    </div>
                    <label for="ticket-reply">Add a reply or note</label>
                    @error('body')<span class="form-field-error">{{ $message }}</span>@enderror
                    <textarea id="ticket-reply" name="body" maxlength="5000" placeholder="Write a clear update..." required>{{ old('body') }}</textarea>
                    <div class="reply-composer-footer">
                        <label class="internal-note-toggle"><input type="checkbox" name="is_internal" value="1" @checked(old('is_internal'))> Add as an internal note</label>
                        <button class="workspace-button workspace-button--primary" type="submit">Add comment <span aria-hidden="true">→</span></button>
                    </div>
                </form>
            </section>
        </div>

        <aside class="ticket-detail-sidebar">
            <section class="workspace-card ticket-management-card">
                <div class="workspace-card-heading"><div><span class="workspace-eyebrow">Manage request</span><h2>Ticket properties</h2></div></div>
                <form action="{{ route('staff.ticket-update', $ticket) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="ticket-property-fields">
                        <label class="workspace-field"><span>Status</span><select name="status" required>
                            @foreach (['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold', 'Resolved', 'Closed'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $ticket->status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select></label>
                        <label class="workspace-field"><span>Priority</span><select name="priority" required>
                            @foreach (['Urgent', 'High', 'Medium', 'Low'] as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', $ticket->priority) === $priority)>{{ $priority }}</option>
                            @endforeach
                        </select></label>
                        <label class="workspace-field"><span>Assigned to</span><select name="assigned_to_user_id">
                            <option value="">Unassigned</option>
                            @foreach ($staffUsers as $staffUser)
                                <option value="{{ $staffUser->id }}" @selected((string) old('assigned_to_user_id', $ticket->assigned_to_user_id) === (string) $staffUser->id)>{{ $staffUser->name }}</option>
                            @endforeach
                        </select></label>
                    </div>
                    <button class="workspace-button workspace-button--primary workspace-button--full" type="submit">Save changes</button>
                </form>
            </section>

            <section class="workspace-card assignment-card">
                <div class="workspace-card-heading"><div><span class="workspace-eyebrow">Ownership</span><h2>Assignment</h2></div></div>
                @if ($ticket->assignedTo)
                    <div class="assignee-row"><span class="conversation-avatar conversation-avatar--staff" aria-hidden="true">{{ strtoupper(substr($ticket->assignedTo->name, 0, 2)) }}</span><div><strong>{{ $ticket->assignedTo->name }}</strong><span>Primary assignee</span></div></div>
                @else
                    <div class="ticket-no-attachments"><span aria-hidden="true">○</span><span>This ticket is currently unassigned.</span></div>
                @endif
                <div class="ticket-assignment-meta"><span>Created</span><strong>{{ $ticket->created_at->format('M j, Y') }}</strong></div>
                @if ($ticket->assigned_at)
                    <div class="ticket-assignment-meta"><span>Assigned</span><strong>{{ $ticket->assigned_at->format('M j, Y') }}</strong></div>
                @endif
            </section>

            <section class="workspace-card ticket-timing-card">
                <div class="workspace-card-heading"><div><span class="workspace-eyebrow">SERVICE TIMING</span><h2>Time tracking</h2></div></div>
                <dl class="ticket-timing-list">
                    <div><dt>Time to first assignment</dt><dd>{{ $ticketTiming['first_assignment'] ?? 'Not assigned yet' }}</dd></div>
                    <div><dt>Latest resolution interval</dt><dd>{{ $ticketTiming['latest_resolution'] ?? 'Not resolved yet' }}</dd></div>
                    <div><dt>Total elapsed to last resolution</dt><dd>{{ $ticketTiming['total_elapsed'] ?? 'Not resolved yet' }}</dd></div>
                </dl>
                <p class="ticket-timing-note">Durations use calendar time, not active work time. The latest resolution interval starts at submission or the latest reopening; total elapsed starts at submission.</p>
            </section>

            <section class="workspace-card client-report-card">
                <div class="workspace-card-heading"><div><span class="workspace-eyebrow">CLIENT CONDUCT</span><h2>Report client</h2></div></div>
                <p class="client-report-intro">Use this for spam, abusive messages, or repeated misuse. An administrator reviews reports before issuing a warning or blocking the client's email.</p>
                @forelse ($ticket->misuseReports as $report)
                    <div class="client-report-history">
                        <strong>{{ str($report->reason)->replace('_', ' ')->title() }}</strong>
                        <span>{{ ucfirst($report->status) }}{{ $report->action ? ' · '.ucfirst($report->action) : '' }}</span>
                        <small>Reported by {{ $report->reporter->name }} · {{ $report->created_at->format('M j, Y') }}</small>
                    </div>
                @empty
                    <p class="client-report-empty">No reports have been submitted for this ticket.</p>
                @endforelse
                <form class="client-report-form" action="{{ route('staff.ticket-misuse-reports.store', $ticket) }}" method="post">
                    @csrf
                    <label class="workspace-field">
                        <span>Reason</span>
                        <select name="reason" required>
                            <option value="">Select a reason</option>
                            <option value="spam" @selected(old('reason') === 'spam')>Spam or repeated duplicate tickets</option>
                            <option value="abusive" @selected(old('reason') === 'abusive')>Abusive or threatening content</option>
                            <option value="irrelevant" @selected(old('reason') === 'irrelevant')>Clearly irrelevant or deliberately false request</option>
                            <option value="repeated" @selected(old('reason') === 'repeated')>Repeated misuse after prior warning</option>
                            <option value="other" @selected(old('reason') === 'other')>Other conduct concern</option>
                        </select>
                        @error('reason')<span class="form-field-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="workspace-field">
                        <span>Details for admin review</span>
                        <textarea name="details" maxlength="1000" placeholder="Describe the behavior and relevant context.">{{ old('details') }}</textarea>
                        @error('details')<span class="form-field-error">{{ $message }}</span>@enderror
                    </label>
                    <button class="workspace-button workspace-button--secondary" type="submit">Submit report for review</button>
                </form>
            </section>

            <section class="workspace-card ticket-timeline-card">
                <div class="workspace-card-heading"><div><span class="workspace-eyebrow">History</span><h2>Ticket activity</h2></div></div>
                <ol class="ticket-timeline">
                    @forelse ($ticket->events as $event)
                        <li><span class="timeline-marker" aria-hidden="true"></span><div><strong>{{ $event->description }}</strong><small>{{ $event->actor?->name ?? 'Requester' }} · {{ $event->created_at->format('M j, Y g:i A') }}</small></div></li>
                    @empty
                        <li><span class="timeline-marker" aria-hidden="true"></span><div><strong>Ticket received</strong><small>{{ $ticket->created_at->format('M j, Y g:i A') }}</small></div></li>
                    @endforelse
                </ol>
            </section>
        </aside>
    </div>
</div>
@endsection
