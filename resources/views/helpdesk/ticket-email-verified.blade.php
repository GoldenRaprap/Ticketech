@extends('layouts.public')
@section('title', 'Email Verified')
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
        <p class="ticket-confirmation-eyebrow">PCU Helpdesk</p>
        <h1>{{ $alreadyVerified ? 'Email Already Verified' : 'Email Verified' }}</h1>
        @if ($alreadyVerified)
            <p class="ticket-confirmation-intro">The email address for ticket <strong>{{ $ticketNumber }}</strong> has already been verified. No additional confirmation email was sent.</p>
        @else
            <p class="ticket-confirmation-intro">The email address for ticket <strong>{{ $ticketNumber }}</strong> is verified. We sent your ticket confirmation and PDF status copy to your inbox.</p>
        @endif
        <a class="view-ticket-button ticket-confirmation-button" href="{{ route('ticket.check') }}">Check Ticket Status</a>
        <a class="ticket-confirmation-home" href="{{ route('home') }}">Return to Helpdesk</a>
    </div>
</section>
@endsection
