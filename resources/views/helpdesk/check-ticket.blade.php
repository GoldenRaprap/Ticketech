@extends('layouts.public')
@section('title', 'View Existing Tickets')
@section('content')
<section class="help-scene ticket-lookup-page">
    <div class="scene-shapes" aria-hidden="true">
        <span class="shape shape-one"></span>
        <span class="shape shape-two"></span>
        <span class="shape shape-three"></span>
        <span class="shape shape-four"></span>
        <span class="shape shape-five"></span>
        <span class="shape shape-six"></span>
    </div>

    <div class="lookup-panel {{ $ticket ? 'lookup-panel--ticket-view' : '' }}">
        @if (! $ticket)
            <div class="ticket-lookup-intro">
                <h2>Check your request</h2>
                <p class="form-intro">Enter the ticket number from your confirmation email and the email address used to submit it. No account is needed.</p>
            </div>

            @if (session('ticket_view_closed'))
                <p class="ticket-view-closed" role="status">{{ session('ticket_view_closed') }}</p>
            @endif

            <form class="lookup-form" action="{{ route('ticket.lookup') }}" method="post">
                @csrf
                @if ($errors->any())
                    <ul class="form-validation-errors" role="alert">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <label class="field-label" for="ticket-number">
                    <span>Ticket number:<b>*</b></span>
                    <input id="ticket-number" name="ticket" type="text" value="{{ old('ticket', $lookupTicketNumber) }}" placeholder="e.g. ED-2026-000123" autocapitalize="characters" autocomplete="off" required>
                </label>
                <label class="field-label" for="lookup-email">
                    <span>Email:<b>*</b></span>
                    <input id="lookup-email" type="email" name="email" value="{{ old('email', $lookupEmail ?: $rememberedEmail) }}" autocomplete="email" required>
                </label>

                <label class="remember-box" for="remember-email">
                    <input id="remember-email" name="remember_email" value="1" type="checkbox" @checked(old('remember_email', $rememberedEmail !== ''))>
                    <span>Remember my e-mail address</span>
                </label>

                <button type="submit" class="view-ticket-button">View Ticket</button>
            </form>

            <p class="lookup-help">Your ticket number is included in the confirmation email sent after submission.</p>
        @endif

        @if ($lookupAttempted && ! $ticket)
            <p class="ticket-lookup-message" role="status">No ticket matched those details. Check the ticket ID and email, then try again.</p>
        @elseif ($ticket)
            @if ($moderationWarning)
                <p class="client-conduct-warning" role="status"><strong>PCU Helpdesk conduct warning:</strong> This email has an active warning. Please use the helpdesk only for genuine support requests.</p>
            @endif
            @if (session('ticket_reply_status'))
                <p class="ticket-reply-success" role="status">
                    <span class="ticket-reply-success-icon" aria-hidden="true">✓</span>
                    {{ session('ticket_reply_status') }}
                </p>
            @endif

            <section class="ticket-lookup-result ticket-view" aria-label="Ticket details">
                <header class="ticket-view-header">
                    <div class="ticket-view-topline">
                        <span class="ticket-view-eyebrow">Your support request</span>
                        <div class="ticket-view-actions">
                            <span class="ticket-view-number">{{ $ticket->ticket_number }}</span>
                            <form action="{{ route('ticket.view.close') }}" method="post">
                                @csrf
                                <button class="ticket-view-close-button" type="submit" aria-label="Close ticket view and hide these details">
                                    <span aria-hidden="true">×</span> Close ticket view
                                </button>
                            </form>
                        </div>
                    </div>
                    <h1>{{ $ticket->subject }}</h1>
                    <p class="ticket-view-created">Submitted {{ $ticket->created_at->format('F j, Y \a\t g:i A') }}</p>
                </header>

                <dl class="ticket-view-summary">
                    <div class="ticket-view-status">
                        <dt>Current status</dt>
                        <dd><span class="ticket-view-status-badge ticket-view-status-badge--{{ \Illuminate\Support\Str::slug($ticket->status) }}">{{ $ticket->status }}</span></dd>
                    </div>
                    <div>
                        <dt>Priority</dt>
                        <dd><span class="ticket-view-priority ticket-view-priority--{{ \Illuminate\Support\Str::lower($ticket->priority) }}">{{ $ticket->priority }}</span></dd>
                    </div>
                    <div>
                        <dt>Ticket number</dt>
                        <dd>{{ $ticket->ticket_number }}</dd>
                    </div>
                </dl>

                <section class="ticket-conversation" aria-labelledby="ticket-conversation-title">
                    <div class="ticket-conversation-heading">
                        <div>
                            <span class="ticket-view-eyebrow">Keep everything in one place</span>
                            <h2 id="ticket-conversation-title">Ticket conversation</h2>
                        </div>
                        <span class="ticket-conversation-count">{{ $ticket->comments->count() + 1 }} {{ \Illuminate\Support\Str::plural('message', $ticket->comments->count() + 1) }}</span>
                    </div>

                    <div class="ticket-conversation-list">
                        <article class="ticket-message ticket-message--requester">
                            <div class="ticket-message-avatar" aria-hidden="true">{{ strtoupper(substr($ticket->requester_name, 0, 1)) }}</div>
                            <div class="ticket-message-content">
                                <div class="ticket-message-meta">
                                    <strong>{{ $ticket->requester_name }}</strong>
                                    <span>Original request</span>
                                    <time datetime="{{ $ticket->created_at->toIso8601String() }}">{{ $ticket->created_at->format('M j, Y g:i A') }}</time>
                                </div>
                                <p>{{ $ticket->description }}</p>
                                @if ($ticket->attachments->isNotEmpty())
                                    <div class="ticket-message-attachments">
                                        <span class="ticket-message-attachment-label">Attached files</span>
                                        @foreach ($ticket->attachments as $attachment)
                                            <form class="ticket-attachment-download" action="{{ route('ticket.attachments.download', $attachment) }}" method="post">
                                                @csrf
                                                <input type="hidden" name="ticket" value="{{ $lookupTicketNumber }}">
                                                <input type="hidden" name="email" value="{{ $lookupEmail }}">
                                                <button type="submit">Download {{ $attachment->original_name }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>

                        @foreach ($ticket->comments as $comment)
                            @php($isRequesterComment = $comment->author_id === null && $comment->author_name === $ticket->requester_name)
                            @php($publicAuthorName = $isRequesterComment ? 'You' : 'PCU IT Support')
                            <article class="ticket-message {{ $isRequesterComment ? 'ticket-message--requester' : 'ticket-message--staff' }}">
                                <div class="ticket-message-avatar" aria-hidden="true">
                                    @if ($isRequesterComment)
                                        {{ strtoupper(substr($ticket->requester_name, 0, 1)) }}
                                    @else
                                        <img src="{{ asset('images/Philippine_Christian_University_logo.png') }}" alt="">
                                    @endif
                                </div>
                                <div class="ticket-message-content">
                                    <div class="ticket-message-meta">
                                        <strong>{{ $publicAuthorName }}</strong>
                                        <span>{{ $isRequesterComment ? 'Your reply' : 'Support team' }}</span>
                                        <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M j, Y g:i A') }}</time>
                                    </div>
                                    <p>{{ $comment->body }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                @if (in_array($ticket->status, ['Resolved', 'Closed'], true))
                    <aside class="ticket-status-notice" role="status">
                        <p><strong>This ticket is currently marked: {{ $ticket->status }}.</strong></p>
                        <p>Only reply if the issue is still happening or comes up again. Your reply will reopen this ticket so PCU IT Support can assist further.</p>
                    </aside>
                @endif

                <section class="ticket-reply-panel" aria-labelledby="client-ticket-reply-heading">
                    <div class="ticket-reply-panel-heading">
                        <span class="ticket-reply-icon" aria-hidden="true">↩</span>
                        <div>
                            <h2 id="client-ticket-reply-heading">Have more to add?</h2>
                            <p>Reply here to continue this conversation. Your message will go straight to the helpdesk team.</p>
                        </div>
                    </div>
                    <form class="ticket-reply-form" action="{{ route('ticket.reply') }}" method="post">
                        @csrf
                        <input type="hidden" name="ticket" value="{{ $lookupTicketNumber }}">
                        <input type="hidden" name="email" value="{{ $lookupEmail }}">
                        <label class="visually-hidden" for="client-ticket-reply">Your message</label>
                        @error('body')
                            <span class="form-field-error">{{ $message }}</span>
                        @enderror
                        <textarea id="client-ticket-reply" name="body" maxlength="5000" placeholder="Type your reply to the PCU Helpdesk..." required>{{ old('body') }}</textarea>
                        <div class="ticket-reply-form-footer">
                            <span>Your reply will be visible to the support team.</span>
                            <button type="submit" class="view-ticket-button">
                                Send reply <span aria-hidden="true">→</span>
                            </button>
                        </div>
                    </form>
                </section>

                <details class="ticket-change-lookup">
                    <summary>Need to check a different ticket?</summary>
                    <form class="lookup-form" action="{{ route('ticket.lookup') }}" method="post">
                        @csrf
                        <label class="field-label" for="ticket-number">
                            <span>Ticket number:<b>*</b></span>
                            <input id="ticket-number" name="ticket" type="text" placeholder="e.g. ED-2026-000123" autocapitalize="characters" autocomplete="off" required>
                        </label>
                        <label class="field-label" for="lookup-email">
                            <span>Email:<b>*</b></span>
                            <input id="lookup-email" type="email" name="email" value="{{ $rememberedEmail }}" autocomplete="email" required>
                        </label>
                        <button type="submit" class="view-ticket-button">View ticket</button>
                    </form>
                </details>
            </section>
        @endif
    </div>
</section>
@endsection