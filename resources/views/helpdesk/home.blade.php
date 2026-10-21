@extends('layouts.public')
@section('title', 'Home')
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
        <p class="home-eyebrow">PHILIPPINE CHRISTIAN UNIVERSITY</p>
        <h1>How can we help you today?</h1>
        <p class="home-intro">Get support for your classes, campus services, and university technology.</p>
        @auth
            <p class="home-welcome">Welcome back, {{ auth()->user()->name }}.</p>
        @endauth

        <div class="help-grid">
            <a class="option-card" href="{{ route('ticket.create') }}">
                <span class="option-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 3.5h9a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2zm2 5h5m-5 4h5m-6 4h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="option-copy">
                    <strong>Submit a Ticket</strong>
                    <small>Tell us what you need and we’ll route it to the right team.</small>
                </span>
            </a>

            <a class="option-card" href="{{ route('ticket.check') }}">
                <span class="option-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 4.5h9a2 2 0 0 1 2 2v11.5a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2zm1.5 7h5m-5 4h5M9 3v3m6-3v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="option-copy">
                    <strong>View Your Tickets</strong>
                    <small>Check request status and read the latest support updates.</small>
                </span>
            </a>
        </div>

        <p class="home-note">No account needed. Submit a request or check its status using your ticket number and email.</p>

        @guest
            <a class="admin-link" href="{{ route('login') }}">Staff and administrator sign in</a>
        @else
            @if (auth()->user()->isAdmin())
                <a class="admin-link" href="{{ route('admin.dashboard') }}">Open Admin Console</a>
            @elseif (auth()->user()->isStaff())
                <a class="admin-link" href="{{ route('staff.dashboard') }}">Open Staff Workspace</a>
            @endif
        @endguest

        @if ($recentTickets->isNotEmpty())
            <section class="home-recent-tickets" aria-label="Recent requests">
                <h3>Recent requests</h3>
                @foreach ($recentTickets as $ticket)
                    <div class="home-recent-ticket">
                        <strong>{{ $ticket->ticket_number }}</strong>
                        <span>{{ $ticket->subject }}</span>
                        <span>{{ $ticket->status }}</span>
                    </div>
                @endforeach
            </section>
        @endif
    </div>
</section>
@endsection