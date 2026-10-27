<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a New Password | PCU Helpdesk</title>
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
                <a href="{{ route('home') }}">Go Back</a>
            </nav>
        </div>
    </header>
    <main class="password-page">
        <section class="password-main" aria-label="Reset password">
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
                    <h1>Choose a new password</h1>
                    <p>Use at least 8 characters. Reset links expire after 60 minutes.</p>

                    <form class="password-form" action="{{ route('password.update') }}" method="post">
                        @csrf
                        @if ($errors->any())
                            <ul class="form-validation-errors" role="alert">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <input type="hidden" name="token" value="{{ $token }}">
                        <label for="reset-email">University email</label>
                        <input id="reset-email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                        <label for="reset-password">New password</label>
                        <div class="password-input-row">
                            <input id="reset-password" name="password" type="password" autocomplete="new-password" required>
                            <button class="password-input-toggle" type="button" data-password-toggle aria-controls="reset-password" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                        <label for="reset-password-confirmation">Confirm new password</label>
                        <div class="password-input-row">
                            <input id="reset-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                            <button class="password-input-toggle" type="button" data-password-toggle aria-controls="reset-password-confirmation" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                        <button type="submit">Save new password</button>
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
