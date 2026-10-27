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

    <div class="lookup-panel ticket-confirmation-panel">
        <div class="ticket-confirmation-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <p class="ticket-confirmation-eyebrow">Request received</p>
        <h1>Ticket Submitted</h1>
        <p class="ticket-confirmation-intro">Your request is in the PCU support queue. Save your ticket number to check its status later.</p>
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
    </div>
</section>
@endsection