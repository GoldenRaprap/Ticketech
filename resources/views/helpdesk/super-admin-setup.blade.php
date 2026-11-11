<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Initial Super Admin Setup | PCU Helpdesk</title>
    <link rel="icon" type="image/png" href="{{ asset('images/ticketech-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="password-page-body">
    <header class="password-header">
        <div class="container public-topbar-inner">
            <a class="password-header-brand" href="{{ route('home') }}">
                <img src="{{ asset('images/Philippine_Christian_University_logo.png') }}" alt="">
                <span>PCU Helpdesk</span>
            </a>
        </div>
    </header>

    <main class="password-page">
        <section class="password-main" aria-label="Initial Super Admin setup">
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
                    <h1>Create the Super Admin account</h1>
                    <p>This is the first account that will manage the helpdesk. You must verify its email before signing in.</p>

                    <form class="password-form" action="{{ route('super-admin-setup.store') }}" method="post">
                        @csrf
                        @if ($errors->any())
                            <ul class="form-validation-errors" role="alert">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <label for="setup-name">Full name</label>
                        <input id="setup-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" @if ($errors->has('name')) aria-invalid="true" @endif required>

                        <label for="setup-email">Administrator email</label>
                        <input id="setup-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" @if ($errors->has('email')) aria-invalid="true" @endif required>

                        <label for="setup-password">Password</label>
                        <div class="password-input-row">
                            <input id="setup-password" name="password" type="password" autocomplete="new-password" minlength="12" @if ($errors->has('password')) aria-invalid="true" @endif required>
                            <button class="password-input-toggle" type="button" data-password-toggle aria-controls="setup-password" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>

                        <label for="setup-password-confirmation">Confirm password</label>
                        <div class="password-input-row">
                            <input id="setup-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                            <button class="password-input-toggle" type="button" data-password-toggle aria-controls="setup-password-confirmation" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>

                        <label for="setup-key">One-time setup key</label>
                        <input id="setup-key" name="setup_key" type="password" autocomplete="off" @if ($errors->has('setup_key')) aria-invalid="true" @endif required>

                        <button type="submit">Create Super Admin account</button>
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
