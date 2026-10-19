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

    <div class="lookup-panel">
        <h2>Check your request</h2>
        <p class="form-intro">Enter the ticket number from your confirmation email and the email address used to submit it. No account is needed.</p>

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

        @if ($lookupAttempted && ! $ticket)
            <p class="ticket-lookup-message" role="status">No ticket matched those details. Check the ticket ID and email, then try again.</p>
        @elseif ($ticket)
            <section class="ticket-lookup-result" aria-label="Ticket details">
                <h3>{{ $ticket->ticket_number }}</h3>
                <dl>
                    <div><dt>Subject</dt><dd>{{ $ticket->subject }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $ticket->status }}</dd></div>
                    <div><dt>Priority</dt><dd>{{ $ticket->priority }}</dd></div>
                    <div><dt>Department</dt><dd>{{ $ticket->department->name }}</dd></div>
                    <div><dt>Category</dt><dd>{{ $ticket->category?->name ?? 'Other' }}</dd></div>
                    <div><dt>Submitted</dt><dd>{{ $ticket->created_at->format('F j, Y g:i A') }}</dd></div>
                </dl>
                <div class="ticket-lookup-description">{{ $ticket->description }}</div>

                @if ($ticket->attachments->isNotEmpty())
                    <h4>Attachments</h4>
                    @foreach ($ticket->attachments as $attachment)
                        <form class="ticket-attachment-download" action="{{ route('ticket.attachments.download', $attachment) }}" method="post">
                            @csrf
                            <input type="hidden" name="ticket" value="{{ $lookupTicketNumber }}">
                            <input type="hidden" name="email" value="{{ $lookupEmail }}">
                            <button type="submit">Download {{ $attachment->original_name }}</button>
                        </form>
                    @endforeach
                @endif

                @if ($ticket->comments->isNotEmpty())
                    <h4>Updates</h4>
                    @foreach ($ticket->comments as $comment)
                        <article class="ticket-public-comment">
                            <strong>{{ $comment->author_name }}</strong>
                            <p>{{ $comment->body }}</p>
                            <small>{{ $comment->created_at->format('F j, Y g:i A') }}</small>
                        </article>
                    @endforeach
                @endif
            </section>
        @endif
    </div>
</section>
@endsection