<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PCU Help Center') | PCU Help Center</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-shell @if(request()->routeIs('ticket.create')) ticket-create-shell @endif" style="--pyramid-background: url('{{ asset('images/Background_Pyramids.png') }}');">
    <header class="public-topbar">
        <div class="container public-topbar-inner">
            <a class="public-brand" href="{{ route('home') }}">PCU Help Center</a>
            <nav class="public-nav" aria-label="Main navigation">
                <a href="{{ route('ticket.create') }}" @if (request()->routeIs('ticket.create')) aria-current="page" @endif>Submit a Ticket</a>
                <a href="{{ route('ticket.check') }}" @if (request()->routeIs('ticket.check')) aria-current="page" @endif>View Your Tickets</a>
                @guest
                    <span class="public-divider" aria-hidden="true">|</span>
                    <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif>Staff sign in</a>
                @endguest
                @auth
                    @if (auth()->user()->isStaff())
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('staff.dashboard') }}">{{ auth()->user()->isAdmin() ? 'Admin console' : 'Staff portal' }}</a>
                    @endif
                    <form class="public-logout" action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit">Log out</button>
                    </form>
                @endauth
            </nav>
        </div>
    </header>

    <div class="crumb-wrap">
        <div class="container breadcrumb">
            <a href="{{ route('home') }}">PCU Help Center</a>
            @if (! request()->routeIs('home'))
                <span>›</span>
                <span aria-current="page">@yield('title')</span>
            @endif
        </div>
    </div>

    @yield('content')

    <footer class="public-footer">
        <div class="public-footer-inner">
            <a class="public-footer-brand" href="{{ route('home') }}">PCU Student &amp; Faculty Support</a>
            <div>Technology help for the Philippine Christian University community</div>
            <div class="public-copyright-bar">© Philippine Christian University {{ now()->year }}</div>
        </div>
    </footer>
</body>
</html>