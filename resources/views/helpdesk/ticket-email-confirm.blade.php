@extends('layouts.public')
@section('title', 'Confirm Ticket Submission')
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
        <h1>Confirm Your Email</h1>
        <p class="ticket-confirmation-intro">Confirm that you can receive email at <strong>{{ $submission->requester_email }}</strong>. We will create and send your ticket to staff only after you confirm.</p>
        <form action="{{ request()->fullUrl() }}" method="post">
            @csrf
            <button class="view-ticket-button ticket-confirmation-button" type="submit">Confirm Email and Submit Ticket</button>
        </form>
        <a class="ticket-confirmation-home" href="{{ route('home') }}">Return to Helpdesk</a>
    </div>
</section>
@endsection
