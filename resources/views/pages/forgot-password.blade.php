<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Ticketech</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="password-page-body">
    <header class="password-header">
        <div class="password-header-brand">PCU HELP CENTER</div>
        <nav aria-label="Main navigation">
            <a href="{{ route('login') }}">Login</a>
            <span>|</span>
            <a href="{{ route('register') }}">Register</a>
        </nav>
    </header>
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
                    <h1>Forgot your password?</h1>
                    <p>Enter your e-mail address below. So we can send you an inbox in your e-mail where you can go to reset your password.</p>

                    <form class="password-form" action="#" method="get">
                        <label for="reset-email">E-mail</label>
                        <input id="reset-email" name="email" type="email">

                        <label for="captcha-input">Captcha</label>
                        <div class="password-captcha" aria-label="Captcha verification">
                            <span>WB3CX</span>
                            <strong aria-hidden="true">⟳</strong>
                        </div>

                        <input id="captcha-input" name="captcha" type="text" aria-label="Enter the characters you see above">
                        <p class="password-captcha-help">Enter the characters you see above.</p>

                        <button type="submit">Send Request</button>
                    </form>
                </div>
            </div>
        </section>

        <footer class="password-footer">
            <div class="password-footer-brand">PCU Internal Help Desk</div>
            <div>Powered by Help Desk Software <span>HESK</span></div>
            <div>More IT firepower? Try <span>SysAid</span></div>
            <div class="password-copyright-bar">© Philippine Christian University 2026</div>
        </footer>
    </main>
</body>
</html>
