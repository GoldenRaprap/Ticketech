<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ticketech') | Ticketech</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <div class="container site-header-inner">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark">T</span><span>TICKETTECH</span></a>
            <nav class="top-nav" aria-label="Main navigation">
                <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
                <a href="{{ route('ticket.create') }}">Create a Ticket</a>
                <a href="{{ route('ticket.check') }}">Check a Ticket</a>
                <a href="{{ route('knowledge-base') }}">Knowledge Base</a>
                <a class="nav-cta" href="{{ route('staff.login') }}">Staff Portal</a>
            </nav>
        </div>
    </header>
    @yield('content')
    <footer class="site-footer">
        <div class="container" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <span><strong style="color:var(--navy)">TICKETTECH</strong> · School IT Help Desk and Ticketing System</span>
            <span>Need help? Submit a ticket and our IT team will follow up.</span>
        </div>
    </footer>
</body>
</html>