<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Registration | PCU Helpdesk</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="password-page-body">
    <header class="password-header">
        <div class="container public-topbar-inner">
            <a class="password-header-brand" href="{{ route('home') }}">
                <img src="{{ asset('images/Philippine_Christian_University_logo.png') }}" alt="">
                <span>PCU Helpdesk</span>
            </a>
            <nav aria-label="Main navigation">
                <a class="header-sign-in" href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('login') }}">Go Back</a>
            </nav>
        </div>
    </header>
    <div class="crumb-wrap">
        <div class="container breadcrumb">
            <a href="{{ route('home') }}">PCU Helpdesk</a>
            <span>›</span>
            <a href="{{ route('login') }}">Staff Sign In</a>
            <span>›</span>
            <span aria-current="page">Registration</span>
        </div>
    </div>

    <main class="password-page">
        <section class="password-main" aria-label="Staff registration">
            <div class="password-art-panel">
                <div class="password-art-shape password-art-shape--one"></div>
                <div class="password-art-shape password-art-shape--two"></div>
                <div class="password-art-shape password-art-shape--three"></div>
                <div class="password-art-shape password-art-shape--four"></div>
                <div class="password-art-shape password-art-shape--five"></div>
                <div class="password-seal" aria-label="Philippine Christian University seal">
                    <img src="{{ asset('images/Philippine_Christian_University_logo.png') }}" alt="Philippine Christian University logo">
                </div>
            </div>

            <div class="password-form-panel">
                <div class="password-form-inner">
                    <p class="password-eyebrow">PCU STAFF PORTAL</p>
                    <h1>Register for staff access</h1>
                    <p>Verify your university email, then an administrator will review your request before staff access is enabled.</p>

                    <form class="password-form" action="{{ route('register.store') }}" method="post">
                        @csrf
                        @if ($errors->any())
                            <ul class="form-validation-errors" role="alert">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <label for="register-name">Full name</label>
                        <input id="register-name" name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Jamie Santos" autocomplete="name" maxlength="255" @if ($errors->has('name')) aria-invalid="true" @endif required>

                        <label for="register-email">PCU staff email</label>
                        <input id="register-email" name="email" type="email" value="{{ old('email') }}" placeholder="e.g. jamie.santos@pcu.edu.ph" autocomplete="email" maxlength="255" @if ($errors->has('email')) aria-invalid="true" @endif required>

                        <label for="register-password">Password</label>
                        <input id="register-password" name="password" type="password" placeholder="At least 12 characters" autocomplete="new-password" minlength="12" @if ($errors->has('password')) aria-invalid="true" @endif required>

                        <label for="register-password-confirmation">Confirm password</label>
                        <input id="register-password-confirmation" name="password_confirmation" type="password" placeholder="Re-enter your password" autocomplete="new-password" minlength="12" required>

                        <button type="submit">Create account</button>
                        <a class="auth-back-link" href="{{ route('login') }}">Already registered? Sign in</a>
                    </form>
                </div>
            </div>
        </section>

        <footer class="password-footer">
            <div class="password-footer-brand">PCU Student &amp; Faculty Support</div>
            <div>Technology help for the Philippine Christian University community</div>
            <div class="password-copyright-bar">© Philippine Christian University {{ now()->year }}</div>
        </footer>
    </main>
</body>
</html>
