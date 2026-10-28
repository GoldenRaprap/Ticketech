<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Staff Workspace') | Ticketech</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="staff-shell sidebar-collapsed" data-staff-shell>
    @include('partials.staff-sidebar')
    <div class="staff-main">
        <header class="staff-header">
            <div class="staff-header-left">
                <button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="staff-sidebar" aria-expanded="false" data-sidebar-toggle>
                    <svg data-sidebar-toggle-icon="menu" aria-hidden="true" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <svg data-sidebar-toggle-icon="arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M9.5 4v16.25m6.75-11.5-3 3 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <img class="staff-header-logo" src="{{ asset('images/Philippine_Christian_University_logo.png') }}" alt="">
                <div class="staff-header-title">@yield('heading', auth()->user()->isAdmin() ? 'Admin Console' : 'Staff Workspace')</div>
            </div>
            <div class="staff-badge-row">
                <span class="workspace-role">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Helpdesk Staff' }}</span>
                <a class="primary-button" href="{{ route('staff.tickets') }}">View Tickets</a>
            </div>
        </header>
        <main class="staff-content">@yield('content')</main>
    </div>
    <script>
        const staffShell = document.querySelector('[data-staff-shell]');
        const sidebarToggles = document.querySelectorAll('[data-sidebar-toggle]');
        const mobileSidebar = window.matchMedia('(max-width: 820px)');
        const sidebarPreferenceKey = 'ticketech.staff-sidebar-collapsed';
        let isDesktopSidebarCollapsed = true;

        try {
            const savedPreference = window.localStorage.getItem(sidebarPreferenceKey);

            if (savedPreference !== null) {
                isDesktopSidebarCollapsed = savedPreference === 'true';
            }
        } catch (error) {
            console.warn('Unable to read the saved sidebar preference.', error);
        }

        function applyDesktopSidebarPreference() {
            staffShell.classList.toggle('sidebar-collapsed', isDesktopSidebarCollapsed);
        }

        function updateSidebarToggle() {
            const isExpanded = mobileSidebar.matches
                ? staffShell.classList.contains('menu-open')
                : !staffShell.classList.contains('sidebar-collapsed');

            sidebarToggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', String(isExpanded));
                toggle.setAttribute('aria-label', mobileSidebar.matches
                    ? (isExpanded ? 'Close navigation' : 'Open navigation')
                    : (isExpanded ? 'Collapse sidebar' : 'Expand sidebar'));
                toggle.querySelector('[data-sidebar-toggle-icon="menu"]').toggleAttribute('hidden', isExpanded);
                toggle.querySelector('[data-sidebar-toggle-icon="arrow"]').toggleAttribute('hidden', !isExpanded);
            });
        }

        sidebarToggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                if (mobileSidebar.matches) {
                    staffShell.classList.toggle('menu-open');
                } else {
                    staffShell.classList.toggle('sidebar-collapsed');
                    isDesktopSidebarCollapsed = staffShell.classList.contains('sidebar-collapsed');

                    try {
                        window.localStorage.setItem(sidebarPreferenceKey, String(isDesktopSidebarCollapsed));
                    } catch (error) {
                        console.warn('Unable to save the sidebar preference.', error);
                    }
                }

                updateSidebarToggle();
            });
        });

        mobileSidebar.addEventListener('change', function () {
            staffShell.classList.remove('menu-open');

            if (mobileSidebar.matches) {
                staffShell.classList.add('sidebar-collapsed');
            } else {
                applyDesktopSidebarPreference();
            }

            updateSidebarToggle();
        });

        if (mobileSidebar.matches) {
            staffShell.classList.add('sidebar-collapsed');
        } else {
            applyDesktopSidebarPreference();
        }

        updateSidebarToggle();
    </script>
</body>
</html>