@extends('layouts.public')
@section('title', 'Submit a Ticket')
@section('content')
<section class="help-scene ticket-form-page {{ $ticketSubmissionOpen ? '' : 'ticket-form-page--closed' }}">
    <div class="scene-shapes" aria-hidden="true">
        <span class="shape shape-one"></span>
        <span class="shape shape-two"></span>
        <span class="shape shape-three"></span>
        <span class="shape shape-four"></span>
        <span class="shape shape-five"></span>
        <span class="shape shape-six"></span>
    </div>

    <div class="lookup-panel ticket-panel">
        <h2>Submit a support request</h2>
        @if ($ticketSubmissionOpen)
            <p class="form-intro">No account is needed. We will verify that you can receive email at the address you provide before creating your ticket.</p>
            <p class="required-note">Required fields are marked with <span>*</span></p>

            <form class="lookup-form ticket-form" action="{{ route('ticket.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                @if ($errors->any())
                    <ul class="form-validation-errors" role="alert">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

            <label class="field-label" for="ticket-name">
                <span class="field-text">Name:<span class="required-mark">*</span></span>
                <input id="ticket-name" name="name" type="text" value="{{ old('name') }}" placeholder="e.g. James Smith" autocomplete="name" required>
            </label>

            <label class="field-label" for="ticket-student-id">
                <span class="field-text">Student or Employee ID:<span class="required-mark">*</span></span>
                <input id="ticket-student-id" name="student_id" type="text" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" value="{{ old('student_id') }}" placeholder="10 digits" aria-describedby="ticket-student-id-hint" required>
                <small id="ticket-student-id-hint" class="field-hint">Enter exactly 10 digits.</small>
            </label>

            <label class="field-label" for="ticket-email">
                <span class="field-text">Email:<span class="required-mark">*</span></span>
                <input id="ticket-email" name="email" type="email" value="{{ old('email') }}" placeholder="example@pcu.edu.ph" autocomplete="email" required>
            </label>

            <label class="field-label" for="ticket-phone">
                <span class="field-text">Phone Number:</span>
                <input id="ticket-phone" name="phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" value="{{ old('phone') }}" placeholder="e.g. 09171234567" autocomplete="tel" aria-describedby="ticket-phone-hint">
                <small id="ticket-phone-hint" class="field-hint">Optional. Enter 11 digits starting with 09.</small>
            </label>

            <label class="field-label" for="ticket-category">
                <span class="field-text">Category:</span>
                <select id="ticket-category" name="category_id">
                    <option value="">Select a category (optional)</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field-label" for="ticket-subject">
                <span class="field-text">Subject:<span class="required-mark">*</span></span>
                <input id="ticket-subject" name="subject" type="text" value="{{ old('subject') }}" placeholder="e.g. Unable to access my student portal" maxlength="150" required>
                <small class="field-hint">Write a brief email subject describing the issue.</small>
            </label>

            <label class="field-label textarea-label" for="ticket-message">
                <span class="field-text">Message:<span class="required-mark">*</span></span>
                <textarea id="ticket-message" name="message" rows="6" maxlength="500" placeholder="Hello, I’m having trouble signing in to my student portal. I see an error after entering my password. Please help me resolve this. Thank you." data-message-counter required>{{ old('message') }}</textarea>
                <small class="field-hint">Write like an email: explain what happened and what help you need.</small>
                <small class="message-counter" aria-live="polite"><span data-message-count>0</span> / 500 characters</small>
            </label>

            <div class="field-label attachment-label">
                <div class="attachment-heading">
                    <span class="field-text">Attachments:</span>
                    <small id="ticket-attachment-count" class="attachment-count" data-attachment-count aria-live="polite"><span>0</span> / 5 files selected</small>
                </div>
                <label class="attachment-box" for="ticket-attachments">
                    <input id="ticket-attachments" name="attachment[]" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple aria-describedby="ticket-attachment-help ticket-attachment-count ticket-attachment-limit ticket-attachment-list">
                    <span class="attachment-inner">
                        <span class="attachment-icon">+</span>
                        <span>Choose files or drop them here</span>
                    </span>
                </label>
                <small id="ticket-attachment-help" class="attachment-help">Attach up to 5 PDF, JPG, PNG, DOC, or DOCX files. Each file may be up to 5 MB.</small>
                <small id="ticket-attachment-limit" class="attachment-limit-message" data-attachment-limit-message role="status" hidden></small>
                <ul id="ticket-attachment-list" class="attachment-list" data-attachment-list aria-live="polite" aria-relevant="additions removals"></ul>
            </div>

            <button type="submit" class="submit-ticket-button">Submit Ticket</button>
        </form>
        @else
            <div class="ticket-submission-closed" role="status">
                <h3>Ticket submission is temporarily paused</h3>
                <p>{{ $ticketSubmissionMessage }}</p>
                <p>You can still <a href="{{ route('ticket.check') }}">check the status of an existing ticket</a>.</p>
            </div>
        @endif
    </div>
</section>
@endsection