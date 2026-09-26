@extends('layouts.public')
@section('title', 'Help Center')
@section('content')
<section class="help-scene">
    <div class="scene-shapes" aria-hidden="true">
        <span class="shape shape-one"></span>
        <span class="shape shape-two"></span>
        <span class="shape shape-three"></span>
        <span class="shape shape-four"></span>
        <span class="shape shape-five"></span>
        <span class="shape shape-six"></span>
    </div>

    <div class="help-panel">
        <h2>Need Any Help?</h2>

        <div class="help-grid">
            <a class="option-card" href="{{ route('ticket.create') }}">
                <span class="option-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 3.5h9a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2zm2 5h5m-5 4h5m-6 4h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="option-copy">
                    <strong>Submit a Ticket</strong>
                    <small>Submit a new issue to a department</small>
                </span>
            </a>

            <a class="option-card" href="{{ route('ticket.check') }}">
                <span class="option-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 4.5h9a2 2 0 0 1 2 2v11.5a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2zm1.5 7h5m-5 4h5M9 3v3m6-3v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="option-copy">
                    <strong>View Your Tickets</strong>
                    <small>View the tickets you have submitted</small>
                </span>
            </a>
        </div>

        <a class="admin-link" href="{{ route('staff.dashboard') }}">Go to Administration Panel</a>
    </div>
</section>
@endsection