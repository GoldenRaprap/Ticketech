@extends('layouts.public')
@section('title', 'Ticket Submitted')
@section('content')
<section class="help-scene ticket-submitted-page">
    <div class="scene-shapes" aria-hidden="true">
        <span class="shape shape-one"></span>
        <span class="shape shape-two"></span>
        <span class="shape shape-three"></span>
        <span class="shape shape-four"></span>
        <span class="shape shape-five"></span>
        <span class="shape shape-six"></span>
    </div>

    <div class="lookup-panel ticket-confirmation-panel {{ ($submittedTicket['email_verification_pending'] ?? false) ? 'ticket-confirmation-panel--pending' : '' }}">
        @if ($submittedTicket['email_verification_pending'] ?? false)
            <div class="ticket-confirmation-icon ticket-confirmation-icon--pending" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M3.75 6.75h16.5v10.5H3.75zM4.5 7.5l7.5 6 7.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        @else
            <div class="ticket-confirmation-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        @endif
        @if ($submittedTicket['email_verification_pending'] ?? false)
            <p class="ticket-confirmation-eyebrow">Action required · email verification</p>
            <h1>Check your inbox</h1>
            <p class="ticket-confirmation-intro">We sent a secure confirmation link. Confirm your email address to finish submitting your support request.</p>

            <div class="ticket-verification-recipient">
                <span class="ticket-verification-recipient-icon" aria-hidden="true">@</span>
                <span><small>Verification email sent to</small><strong>{{ $submittedTicket['email'] }}</strong></span>
            </div>

            <ol class="ticket-verification-steps" aria-label="Ticket submission steps">
                <li class="ticket-verification-step ticket-verification-step--complete">
                    <span class="ticket-verification-step-number" aria-hidden="true">1</span>
                    <span><strong>Request received</strong><small>Your request is saved securely.</small></span>
                </li>
                <li class="ticket-verification-step ticket-verification-step--current" aria-current="step">
                    <span class="ticket-verification-step-number" aria-hidden="true">2</span>
                    <span><strong>Confirm your email</strong><small>Open the email and select its link.</small></span>
                </li>
                <li class="ticket-verification-step">
                    <span class="ticket-verification-step-number" aria-hidden="true">3</span>
                    <span><strong>Ticket submitted</strong><small>We’ll send it to the support team.</small></span>
                </li>
            </ol>

            <p class="ticket-verification-notice" role="status"><strong>Your ticket has not been created or sent to staff yet.</strong> Check your inbox’s spam or junk folder if you don’t see the email.</p>
            <p class="ticket-verification-expiry">The secure link expires in 24 hours. If it expires, submit your request again.</p>
        @else
            <p class="ticket-confirmation-eyebrow">Request received</p>
            <h1>Ticket Submitted</h1>
            <p class="ticket-confirmation-intro">Your email is verified and your request is in the PCU support queue. A ticket confirmation and PDF status copy were sent to <strong>{{ $submittedTicket['email'] }}</strong>.</p>
            @if ($submittedTicket['moderation_warning'] ?? false)
                <p class="client-conduct-warning" role="status"><strong>PCU Helpdesk conduct warning:</strong> This email has an active warning. Please use the helpdesk only for genuine support requests.</p>
            @endif

            <div class="ticket-confirmation-number">
                <span>Ticket Number</span>
                <strong>{{ $submittedTicket['ticket_number'] }}</strong>
            </div>

            <dl class="ticket-confirmation-details">
                <div>
                    <dt>Date Submitted</dt>
                    <dd>{{ $submittedTicket['submitted_at'] }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><span class="ticket-confirmation-status">{{ $submittedTicket['status'] }}</span></dd>
                </div>
            </dl>

            <div class="ticket-document-actions">
                <form action="{{ route('ticket.download') }}" method="post" target="_blank">
                    @csrf
                    <input type="hidden" name="ticket" value="{{ $submittedTicket['ticket_number'] }}">
                    <input type="hidden" name="email" value="{{ $submittedTicket['email'] }}">
                    <input type="hidden" name="print" value="1">
                    <button class="ticket-document-button" type="submit">Print ticket</button>
                </form>
                <form action="{{ route('ticket.download') }}" method="post">
                    @csrf
                    <input type="hidden" name="ticket" value="{{ $submittedTicket['ticket_number'] }}">
                    <input type="hidden" name="email" value="{{ $submittedTicket['email'] }}">
                    <button class="ticket-document-button" type="submit">Download ticket</button>
                </form>
            </div>

            <a class="view-ticket-button ticket-confirmation-button" href="{{ route('ticket.check') }}">Check Ticket Status</a>
            <a class="ticket-confirmation-home" href="{{ route('home') }}">Return to Helpdesk</a>
        @endif
    </div>
</section>
@endsection