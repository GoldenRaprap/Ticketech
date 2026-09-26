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
        <h2>View Existing Tickets</h2>

        <form class="lookup-form" action="{{ route('ticket.check') }}" method="get">
            <label class="field-label" for="ticket-number">
                <span>Ticket ID:<b>*</b></span>
                <input id="ticket-number" name="ticket" type="text">
            </label>
            <label class="field-label" for="lookup-email">
                <span>Email:<b>*</b></span>
                <input id="lookup-email" type="email" name="email">
            </label>

            <label class="remember-box" for="remember-email">
                <input id="remember-email" type="checkbox" />
                <span>Remember my e-mail address</span>
            </label>

            <button type="submit" class="view-ticket-button">View Ticket</button>
        </form>

        <p class="lookup-help">Forgot your Ticket ID? Check your past e-mails with our domain name.</p>
    </div>
</section>
@endsection