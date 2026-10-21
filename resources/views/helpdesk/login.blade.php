<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Sign In | PCU Helpdesk</title>
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
                <a href="{{ route('home') }}">Helpdesk</a>
            </nav>
        </div>
    </header>
    <div class="crumb-wrap">
        <div class="container breadcrumb">
            <a href="{{ route('home') }}">PCU Helpdesk</a>
            <span>›</span>
            <span aria-current="page">Staff Sign In</span>
        </div>
    </div>

    <main class="password-page">
        <section class="password-main" aria-label="Staff and administrator sign in">
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
                    <p class="password-eyebrow">PCU STAFF PORTAL</p>
                    <h1>Staff Sign In</h1>
                    <p>Use your assigned university account to access the staff or administrator workspace.</p>
                    @if (session('status'))
                        <p class="password-form-status" role="status">{{ session('status') }}</p>
                    @endif

                    <form class="password-form" action="{{ route('login.store') }}" method="post">
                        @csrf
                        @if ($errors->any())
                            <ul class="form-validation-errors" role="alert">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <label for="login-email">University email</label>
                        <input id="login-email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" @if ($errors->has('email')) aria-invalid="true" @endif required>

                        <label for="login-password">Password</label>
                        <div class="password-input-row">
                            <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                            <button class="password-input-toggle" type="button" data-password-toggle aria-controls="login-password" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>

                        <div class="login-form-options">
                            <label class="login-remember" for="remember-login">
                                <input id="remember-login" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                                <span>Remember me</span>
                            </label>
                            <a class="login-forgot-link" href="{{ route('password.request') }}">Forgot password?</a>
                        </div>
                        <button type="submit">Sign in</button>
                    </form>

                    @if (config('app.env') === 'local')
                        <div class="login-preview-actions">
                            <form action="{{ route('login.preview', ['role' => 'staff']) }}" method="post">
                                @csrf
                                <button type="submit">Staff (temporary)</button>
                            </form>
                            <form action="{{ route('login.preview', ['role' => 'admin']) }}" method="post">
                                @csrf
                                <button type="submit">Admin (temporary)</button>
                            </form>
                        </div>
                    @endif
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
