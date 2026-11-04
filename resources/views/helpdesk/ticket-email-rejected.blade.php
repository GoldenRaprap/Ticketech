@extends('layouts.public')
@section('title', 'Ticket Submission Unavailable')
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
        <p class="ticket-confirmation-eyebrow">PCU Helpdesk</p>
        <h1>Ticket Submission Unavailable</h1>
        <p class="ticket-confirmation-intro">Ticket submissions from this email address are blocked. Contact PCU Helpdesk administration if you believe this is a mistake.</p>
        <a class="view-ticket-button ticket-confirmation-button" href="{{ route('home') }}">Return to Helpdesk</a>
    </div>
</section>
@endsection
