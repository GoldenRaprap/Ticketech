@extends('layouts.public')
@section('title', 'Check Your Ticket')
@section('content')
<main class="page-wrap"><div class="container">
    <span class="eyebrow">Request tracking</span><h1 class="page-title">Check Your Ticket</h1>
    <p class="page-intro">Enter your ticket number to view the current status of your request.</p>
    <form class="panel form-panel" action="{{ route('ticket.check') }}" method="get">
        <div class="form-grid">
            <div class="field"><label for="ticket-number">Ticket Number</label><input id="ticket-number" name="ticket" placeholder="ED-2026-00125"></div>
            <div class="field"><label for="lookup-email">Email Address</label><input id="lookup-email" type="email" name="email" placeholder="name@school.edu"></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Check Ticket</button></div>
    </form>
    <section class="panel ticket-preview">
        <div class="panel-heading"><div><span class="ticket-id">ED-2026-00125</span><h2>Cannot access Bluebook</h2></div><span class="badge">In Progress</span></div>
        <div class="detail-strip">
            <div><small>Category</small><strong>Online Learning Platform</strong></div>
            <div><small>Submitted</small><strong>September 26, 2026</strong></div>
            <div><small>Assigned To</small><strong>IT Staff</strong></div>
        </div>
        <div class="ticket-timeline" aria-label="Ticket progress">
            <div class="timeline-step done">Submitted</div><div class="timeline-step done">Assigned</div><div class="timeline-step current">In Progress</div><div class="timeline-step">Resolved</div><div class="timeline-step">Closed</div>
        </div>
    </section>
</div></main>
@endsection