@extends('layouts.public')
@section('title', 'Submit a Ticket')
@section('content')
<section class="help-scene ticket-form-page">
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
        <p class="form-intro">No account is needed. Share a few details and the right PCU support team will follow up by email.</p>
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
                <input id="ticket-name" name="name" type="text" value="{{ old('name') }}" placeholder="Firstname Lastname" autocomplete="name" required>
            </label>

            <label class="field-label" for="ticket-student-id">
                <span class="field-text">Student or Employee ID:<span class="required-mark">*</span></span>
                <input id="ticket-student-id" name="student_id" type="text" value="{{ old('student_id') }}" placeholder="2023******" required>
            </label>

            <label class="field-label" for="ticket-email">
                <span class="field-text">Email:<span class="required-mark">*</span></span>
                <input id="ticket-email" name="email" type="email" value="{{ old('email') }}" placeholder="example@pcu.edu.ph" autocomplete="email" required>
            </label>

            <label class="field-label" for="ticket-phone">
                <span class="field-text">Phone Number:</span>
                <input id="ticket-phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="e.g. 09171234567" autocomplete="tel">
            </label>

            <label class="field-label" for="ticket-priority">
                <span class="field-text">Priority:<span class="required-mark">*</span></span>
                <select id="ticket-priority" name="priority" required>
                    <option value="Urgent" @selected(old('priority', 'Low') === 'Urgent')>Urgent — Critical issue</option>
                    <option value="High" @selected(old('priority', 'Low') === 'High')>High — Work stopped</option>
                    <option value="Medium" @selected(old('priority', 'Low') === 'Medium')>Medium — Work affected</option>
                    <option value="Low" @selected(old('priority', 'Low') === 'Low')>Low — General question</option>
                </select>
                <small class="field-hint">Urgent: critical issue; High: work stopped; Medium: work affected; Low: general question.</small>
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
            </label>

            <div class="field-label attachment-label">
                <span class="field-text">Attachment:</span>
                <label class="attachment-box" for="ticket-attachment">
                    <input id="ticket-attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <span class="attachment-inner">
                        <span class="attachment-icon">+</span>
                        <span>Choose a file or drop it here</span>
                    </span>
                </label>
                <small class="attachment-help">PDF, JPG, PNG, DOC, or DOCX. Maximum file size: 5 MB.</small>
            </div>

            <div class="message-counter" aria-live="polite"><span data-message-count>0</span> / 500 characters</div>

            <button type="submit" class="submit-ticket-button">Submit Ticket</button>
        </form>
    </div>
</section>
@endsection