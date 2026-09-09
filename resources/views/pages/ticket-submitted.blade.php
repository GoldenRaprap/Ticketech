@extends('layouts.public')
@section('title', 'Ticket Submitted')
@section('content')
<main class="page-wrap"><div class="container">
    <section class="panel center-state">
        <span class="success-mark" aria-hidden="true">✓</span>
        <span class="eyebrow">Request received</span>
        <h1>Ticket Submitted Successfully</h1>
        <p>Your concern has been submitted to the Ticketech Help Desk.</p>
        <div class="detail-strip">
            <div><small>Ticket Number</small><strong>ED-2026-00125</strong></div>
            <div><small>Date Submitted</small><strong>September 26, 2026</strong></div>
            <div><small>Status</small><span class="badge">Submitted</span></div>
        </div>
        <a class="btn btn-primary" href="{{ route('ticket.check') }}">Check Ticket Status</a>
    </section>
</div></main>
@endsection