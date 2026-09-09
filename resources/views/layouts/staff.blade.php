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
                <strong>TICKETTECH <span style="color:#8ba0b5;font-weight:400">/</span> Staff Portal</strong>
            </div>
            <a class="staff-user" href="{{ route('staff.profile') }}">
                <span class="avatar">JD</span><span>Jordan Davis · Employee / IT Staff</span>
            </a>
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