<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Staff Workspace') | Ticketech</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="staff-shell" data-staff-shell>
    @include('partials.staff-sidebar')
    <div class="staff-main">
        <header class="staff-header">
            <div class="staff-header-left">
                <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-menu-toggle>☰</button>
                <div class="staff-header-title">@yield('heading', auth()->user()->isAdmin() ? 'Admin Console' : 'Staff Workspace')</div>
            </div>
            <div class="staff-badge-row">
                <span class="workspace-role">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Helpdesk Staff' }}</span>
                <a class="primary-button" href="{{ route('staff.tickets') }}">View Tickets</a>
                <form class="header-logout" action="{{ route('logout') }}" method="post">
                    @csrf
                    <button type="submit">Sign Out</button>
                </form>
            </div>
        </header>
        <main class="staff-content">@yield('content')</main>
    </div>
    <script>
        document.querySelector('[data-menu-toggle]')?.addEventListener('click', function () {
            const shell = document.querySelector('[data-staff-shell]');
            const expanded = shell.classList.toggle('menu-open');
            this.setAttribute('aria-expanded', String(expanded));
        });
    </script>
</body>
</html>