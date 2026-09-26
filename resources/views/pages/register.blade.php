@extends('layouts.public')
@section('title', 'Register')
@section('content')
<section class="help-scene help-scene--compact">
    <div class="scene-shapes" aria-hidden="true">
        <span class="shape shape-one"></span>
        <span class="shape shape-two"></span>
        <span class="shape shape-three"></span>
        <span class="shape shape-four"></span>
        <span class="shape shape-five"></span>
        <span class="shape shape-six"></span>
    </div>

    <div class="lookup-panel register-panel">
        <h2>Create an Account</h2>
        <p class="required-note">Required fields are marked with <span>*</span></p>

        <form class="lookup-form ticket-form" action="{{ route('login') }}" method="get">
            <label class="field-label" for="reg-name">
                <span class="field-text">Name:<span class="required-mark">*</span></span>
                <input id="reg-name" name="name" type="text">
            </label>

            <label class="field-label" for="reg-email">
                <span class="field-text">Email:<span class="required-mark">*</span></span>
                <input id="reg-email" name="email" type="email">
            </label>

            <label class="field-label" for="reg-password">
                <span class="field-text">Password:<span class="required-mark">*</span></span>
                <input id="reg-password" name="password" type="password">
            </label>

            <label class="field-label" for="reg-password-strength">
                <span class="field-text">Password Strength: <span class="muted-text">???</span></span>
                <input id="reg-password-strength" type="text" value="" aria-label="Password strength">
            </label>

            <label class="field-label" for="reg-confirm">
                <span class="field-text">Confirm Password:<span class="required-mark">*</span></span>
                <input id="reg-confirm" name="confirm_password" type="password">
            </label>

            <div class="captcha-box captcha-box--register" aria-label="Captcha verification">
                <div class="captcha-check">
                    <span class="captcha-toggle"></span>
                    <span>I'm not a robot</span>
                </div>
                <div class="captcha-brand">
                    <span class="captcha-logo">reCAPTCHA</span>
                    <small>Privacy - Terms</small>
                </div>
            </div>

            <button type="submit" class="submit-ticket-button register-button">Create Account</button>
        </form>
    </div>
</section>
@endsection
