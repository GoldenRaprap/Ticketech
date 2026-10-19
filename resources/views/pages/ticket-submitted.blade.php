@extends('layouts.public')
@section('title', 'Ticket Submitted')
@section('content')
<main class="page-wrap"><div class="container">
    <section class="panel center-state">
        <span class="success-mark" aria-hidden="true">✓</span>
        <span class="eyebrow">Request received</span>
        <h1>Ticket Submitted Successfully</h1>
        <p>Your request is in the PCU support queue. Keep this ticket number to check its status later.</p>
        <div class="detail-strip">
            <div><small>Ticket Number</small><strong>{{ $submittedTicket['ticket_number'] }}</strong></div>
            <div><small>Date Submitted</small><strong>{{ $submittedTicket['submitted_at'] }}</strong></div>
            <div><small>Status</small><span class="badge">{{ $submittedTicket['status'] }}</span></div>
        </div>
        <a class="btn btn-primary" href="{{ route('ticket.check') }}">Check Ticket Status</a>
    </section>
</div></main>
@endsection