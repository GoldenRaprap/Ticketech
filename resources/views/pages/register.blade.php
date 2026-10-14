@extends('layouts.public')
@section('title', 'Register')
@section('content')
<section class="help-scene help-scene--register">
    <div class="scene-shapes" aria-hidden="true">
        <span class="shape shape-one"></span>
        <span class="shape shape-two"></span>
        <span class="shape shape-three"></span>
        <span class="shape shape-four"></span>
        <span class="shape shape-five"></span>
        <span class="shape shape-six"></span>
    </div>

    <div class="register-panel">
        <h2 id="register-heading">Create an account</h2>
        <p class="register-intro">Enter your details to request a PCU Help Center account.</p>

        <div class="register-form" role="group" aria-labelledby="register-heading">
            <div class="register-field">
                <label for="reg-name">Full name <span aria-hidden="true">*</span></label>
                <input id="reg-name" type="text" autocomplete="name" required>
            </div>

            <div class="register-field">
                <label for="reg-email">Email address <span aria-hidden="true">*</span></label>
                <input id="reg-email" type="email" autocomplete="email" required>
            </div>

            <div class="register-field">
                <label for="reg-password">Password <span aria-hidden="true">*</span></label>
                <input id="reg-password" type="password" autocomplete="new-password" aria-describedby="register-password-hint" required>
                <p id="register-password-hint" class="register-field-hint">Use at least 8 characters.</p>
            </div>

            <div class="register-field">
                <label for="reg-confirm">Confirm password <span aria-hidden="true">*</span></label>
                <input id="reg-confirm" type="password" autocomplete="new-password" required>
            </div>

            <button type="button" class="register-submit" disabled>Create account</button>
            <p class="register-status" role="status">Account registration is not available yet.</p>
            <p class="register-login">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
        </div>
    </div>
</section>
@endsection
