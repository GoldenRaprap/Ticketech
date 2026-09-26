<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ticketech') | Ticketech</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-shell @if(request()->routeIs('ticket.create')) ticket-create-shell @endif" style="--pyramid-background: url('{{ asset('images/Background_Pyramids.png') }}');">
    <header class="public-topbar">
        <div class="container public-topbar-inner">
            <div class="public-brand">PCU HELP CENTER</div>
            <nav class="public-nav" aria-label="Main navigation">
                <a href="{{ route('login') }}">Login</a>
                <span class="public-divider">|</span>
                <a href="{{ route('register') }}">Register</a>
            </nav>
        </div>
    </header>

    <div class="crumb-wrap">
        <div class="container breadcrumb">Website <span>›</span> Help Center</div>
    </div>

    @yield('content')

    <footer class="public-footer">
        <div class="public-footer-inner">
            <div class="public-footer-brand">PCU Internal Help Desk</div>
            <div>Powered by Help Desk Software <span>HESK</span></div>
            <div>More IT firepower? Try <span>SysAid</span></div>
            <div class="public-copyright-bar">© Philippine Christian University 2026</div>
        </div>
    </footer>
</body>
</html>