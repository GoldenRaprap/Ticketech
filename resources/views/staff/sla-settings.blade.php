@extends('layouts.staff')
@section('title', 'SLA Settings')
@section('heading', 'SLA Settings')
@section('content')
<div class="admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / SERVICE POLICY</span>
            <h1>Service level targets</h1>
            <p>Review the response and resolution targets used to guide helpdesk operations.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> Policy overview</span>
    </header>

    <section class="admin-policy-note">
        <span class="admin-policy-note-icon" aria-hidden="true">i</span>
        <div><strong>Targets are informational</strong><p>These default targets are a reference for staff. Editable SLA configuration is not enabled in this console.</p></div>
        <span class="admin-readonly-badge">Current reference</span>
    </section>

    <section class="admin-sla-grid" aria-label="Service targets by priority">
        @foreach ([['Urgent','1 business hour','4 business hours','admin-sla-card--urgent','Immediate attention'],['High','4 business hours','1 business day','admin-sla-card--high','Expedited response'],['Medium','1 business day','3 business days','admin-sla-card--medium','Standard response'],['Low','2 business days','5 business days','admin-sla-card--low','General queue']] as [$priority, $response, $resolution, $class, $description])
            <article class="admin-sla-card {{ $class }}">
                <header><span class="admin-priority-indicator"></span><span>{{ $priority }} priority</span><span class="admin-readonly-badge">Default</span></header>
                <p>{{ $description }}</p>
                <div class="admin-sla-target"><span>First response</span><strong>{{ $response }}</strong></div>
                <div class="admin-sla-target"><span>Target resolution</span><strong>{{ $resolution }}</strong></div>
                <footer><span aria-hidden="true">◷</span> Coverage: school hours</footer>
            </article>
        @endforeach
    </section>

    <section class="admin-console-card">
        <header class="admin-console-card-heading">
            <div><span class="admin-page-eyebrow">HOW TO INTERPRET</span><h2>Service target definitions</h2><p>Apply the same timing definitions consistently across tickets.</p></div>
        </header>
        <div class="admin-definition-grid">
            <article><span class="admin-definition-number">01</span><div><strong>First response</strong><p>Time from ticket submission until a staff member first responds to the requester.</p></div></article>
            <article><span class="admin-definition-number">02</span><div><strong>Resolution</strong><p>Target time to resolve the issue. Reopened tickets need a new response and resolution review.</p></div></article>
            <article><span class="admin-definition-number">03</span><div><strong>Coverage</strong><p>Targets are expressed in school-hour terms; the current workspace timer reports calendar elapsed time.</p></div></article>
        </div>
    </section>
</div>
@endsection
