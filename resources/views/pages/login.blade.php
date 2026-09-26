<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Ticketech</title>
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
        <section class="password-main" aria-label="Login">
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
                <div class="password-form-inner login-form-inner">
                    <h1>Welcome!</h1>
                    <p>Please log in.</p>

                    <form class="password-form" action="{{ route('staff.dashboard') }}" method="get">
                        <label for="login-email">E-mail</label>
                        <input id="login-email" name="email" type="email">

                        <label for="login-password">Password</label>
                        <input id="login-password" name="password" type="password">

                        <a class="login-forgot-link" href="{{ route('password.request') }}">Forgot your password? Click here.</a>
                        <button type="submit">Click to Log-in</button>
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
