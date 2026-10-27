<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Your Email | PCU Helpdesk</title>
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
                <a href="{{ route('home') }}">Go Back</a>
            </nav>
        </div>
    </header>
    <div class="crumb-wrap">
        <div class="container breadcrumb">
            <a href="{{ route('home') }}">PCU Helpdesk</a>
            <span>›</span>
            <span aria-current="page">Verify email</span>
        </div>
    </div>

    <main class="password-page">
        <section class="password-main" aria-label="Verify your email address">
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
                    <h1>Verify your email</h1>
                    <p>We sent a secure verification link to {{ auth()->user()->email }}. Verify this address before your request can be reviewed for staff access.</p>

                    @if (session('status'))
                        <p class="password-form-status" role="status">{{ session('status') }}</p>
                    @endif

                    <form class="password-form" action="{{ route('verification.send') }}" method="post">
                        @csrf
                        <button type="submit">Resend verification email</button>
                        <a class="auth-back-link" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('verification-logout-form').submit();">Sign out</a>
                    </form>
                    <form id="verification-logout-form" action="{{ route('logout') }}" method="post" hidden>
                        @csrf
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
