@extends('layouts.public')
@section('title', 'School IT Help Desk')
@section('content')
<section class="hero">
    <div class="container hero-inner">
        <span class="eyebrow">School IT Help Desk and Ticketing System</span>
        <h1>How can we help you?</h1>
        <p class="lead">Submit a school IT or technology concern and track your request through Ticketech.</p>
        <div class="action-grid">
            <a class="action-card" href="{{ route('ticket.create') }}">
                <span class="action-icon">+</span><span><strong>CREATE A TICKET</strong><span>Submit a new support request</span></span>
            </a>
            <a class="action-card" href="{{ route('ticket.check') }}">
                <span class="action-icon">⌕</span><span><strong>CHECK A TICKET</strong><span>Track an existing ticket</span></span>
            </a>
        </div>
    </div>
</section>
<section class="section">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">Support directory</span><h2>Areas We Support</h2><p>Choose the service that best matches your concern.</p></div></div>
        <div class="support-grid">
            @foreach ([['R','Registrar / Student Records'],['B','Bluebook / Online Learning Platform'],['P','PRIISM / Student Information Portal'],['N','Network / Internet'],['H','Hardware / Equipment'],['S','Software / Applications'],['+','Other IT Concerns']] as [$icon, $label])
                <div class="support-item"><span class="action-icon">{{ $icon }}</span><span>{{ $label }}</span></div>
            @endforeach
        </div>
    </div>
</section>
@endsection