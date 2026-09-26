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
        <h2>Create Ticket</h2>
        <p class="required-note">Required fields are marked with <span>*</span></p>

        <form class="lookup-form ticket-form" action="{{ route('ticket.submitted') }}" method="get">
            <label class="field-label" for="ticket-name">
                <span class="field-text">Name:<span class="required-mark">*</span></span>
                <input id="ticket-name" name="name" type="text" placeholder="">
            </label>

            <label class="field-label" for="ticket-student-id">
                <span class="field-text">Student ID:<span class="required-mark">*</span></span>
                <input id="ticket-student-id" name="student_id" type="text" placeholder="">
            </label>

            <label class="field-label" for="ticket-email">
                <span class="field-text">Email:<span class="required-mark">*</span></span>
                <input id="ticket-email" name="email" type="email" placeholder="">
            </label>

            <label class="field-label" for="ticket-phone">
                <span class="field-text">Phone Number:</span>
                <input id="ticket-phone" name="phone" type="tel" placeholder="">
            </label>

            <label class="field-label" for="ticket-department">
                <span class="field-text">Department:<span class="required-mark">*</span></span>
                <select id="ticket-department" name="department">
                    <option value="" selected disabled></option>
                    <option>College of Engineering</option>
                    <option>College of Education</option>
                    <option>College of Business</option>
                    <option>IT Services</option>
                    <option>Student Affairs</option>
                </select>
            </label>

            <label class="field-label" for="ticket-priority">
                <span class="field-text">Priority:<span class="required-mark">*</span></span>
                <select id="ticket-priority" name="priority">
                    <option value="" selected disabled></option>
                    <option>High</option>
                    <option>Medium</option>
                    <option>Low</option>
                </select>
            </label>

            <label class="field-label" for="ticket-subject">
                <span class="field-text">Subject:<span class="required-mark">*</span></span>
                <input id="ticket-subject" name="subject" type="text" placeholder="">
            </label>

            <label class="field-label textarea-label" for="ticket-message">
                <span class="field-text">Message:<span class="required-mark">*</span></span>
                <textarea id="ticket-message" name="message" rows="6" maxlength="500"></textarea>
            </label>

            <div class="field-label attachment-label">
                <span class="field-text">Attachment:</span>
                <label class="attachment-box" for="ticket-attachment">
                    <input id="ticket-attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <span class="attachment-inner">
                        <span class="attachment-icon">+</span>
                        <span>Drop an attachment</span>
                    </span>
                </label>
            </div>

            <div class="message-counter">Note: Maximum 500 characters</div>

            <button type="submit" class="submit-ticket-button">Submit Ticket</button>
        </form>
    </div>
</section>
@endsection