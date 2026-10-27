<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Password Help | PCU Helpdesk</title>
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
            <span aria-current="page">Forgot Password</span>
        </div>
    </div>
    <main class="password-page">
        <section class="password-main" aria-label="Password reset">
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
                    <h1>Reset your password</h1>
                    <p>Enter your university staff email. If the account is eligible, we’ll send a secure reset link.</p>

                    @if (session('status'))
                        <p class="password-form-status" role="status">{{ session('status') }}</p>
                    @endif

                    <form class="password-form" action="{{ route('password.email') }}" method="post">
                        @csrf
                        @if ($errors->any())
                            <ul class="form-validation-errors" role="alert">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <label for="reset-email">University email</label>
                        <input id="reset-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" @if ($errors->has('email')) aria-invalid="true" @endif required>

                        <button type="submit">Send reset link</button>
                        <a class="auth-back-link" href="{{ route('login') }}">Back to Staff Sign In</a>
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
