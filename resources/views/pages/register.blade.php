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

        <form class="register-form" action="{{ route('register.store') }}" method="post" aria-labelledby="register-heading">
            @csrf
            @if ($errors->any())
                <ul class="form-validation-errors" role="alert">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="register-field">
                <label for="reg-name">Full name <span aria-hidden="true">*</span></label>
                <input id="reg-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required>
            </div>

            <div class="register-field">
                <label for="reg-email">Email address <span aria-hidden="true">*</span></label>
                <input id="reg-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            </div>

            <div class="register-field">
                <label for="reg-password">Password <span aria-hidden="true">*</span></label>
                <input id="reg-password" name="password" type="password" autocomplete="new-password" aria-describedby="register-password-hint" required>
                <p id="register-password-hint" class="register-field-hint">Use at least 8 characters.</p>
            </div>

            <div class="register-field">
                <label for="reg-confirm">Confirm password <span aria-hidden="true">*</span></label>
                <input id="reg-confirm" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>

            <button type="submit" class="register-submit">Create account</button>
            <p class="register-login">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
        </form>
    </div>
</section>
@endsection
