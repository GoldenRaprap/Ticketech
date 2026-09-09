@extends('layouts.public')
@section('title', 'Submit a Ticket')
@section('content')
<main class="page-wrap"><div class="container">
    <span class="eyebrow">Help request</span><h1 class="page-title">Submit a Ticket</h1>
    <p class="page-intro">Tell us what’s happening and the school IT team will review your request.</p>
    <form class="panel form-panel" action="{{ route('ticket.submitted') }}" method="get">
        <div class="form-grid">
            <div class="field"><label for="full-name">Full Name</label><input id="full-name" name="name" placeholder="Your name" required></div>
            <div class="field"><label for="school-id">Student / Employee ID</label><input id="school-id" name="school_id" placeholder="e.g. 202600123"></div>
            <div class="field"><label for="email">Email Address</label><input id="email" type="email" name="email" placeholder="name@school.edu" required></div>
            <div class="field"><label for="contact">Contact Information</label><input id="contact" name="contact" placeholder="Phone number or extension"></div>
            <div class="field"><label for="category">Category</label><select id="category" name="category"><option>Select a category</option><option>Registrar / Student Records</option><option>Bluebook / Online Learning Platform</option><option>PRIISM / Student Information Portal</option><option>Network / Internet</option><option>Hardware / Equipment</option><option>Software / Applications</option><option>Other IT Concern</option></select></div>
            <div class="field"><label for="priority">Priority</label><select id="priority" name="priority"><option>Normal</option><option>Low</option><option>High</option><option>Urgent</option></select></div>
            <div class="field field-wide"><label for="subject">Subject</label><input id="subject" name="subject" placeholder="Briefly describe the issue"></div>
            <div class="field field-wide"><label for="description">Description</label><textarea id="description" name="description" placeholder="Include what happened and any steps you’ve already tried"></textarea></div>
            <div class="field field-wide"><label for="attachment">Attachment</label><input id="attachment" type="file" name="attachment"><small>Prototype only. Files are not uploaded.</small></div>
        </div>
        <div class="form-actions"><a class="btn btn-secondary" href="{{ route('home') }}">Cancel</a><button class="btn btn-primary" type="submit">Submit Ticket</button></div>
    </form>
</div></main>
@endsection