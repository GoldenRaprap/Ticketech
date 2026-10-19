<aside class="staff-sidebar" aria-label="{{ auth()->user()->isAdmin() ? 'Administrator' : 'Staff' }} navigation">
    <a class="sidebar-brand" href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('staff.dashboard') }}">
        <span class="sidebar-brand-icon">◫</span>
        <span>{{ auth()->user()->isAdmin() ? 'HELPDESK ADMIN' : 'HELPDESK STAFF' }}</span>
    </a>

    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('staff.dashboard', 'admin.dashboard') ? 'active' : '' }}" href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('staff.dashboard') }}">
            <span class="sidebar-icon">▣</span>
            <span>Dashboard</span>
        </a>

        <a class="sidebar-link {{ request()->routeIs('staff.tickets', 'staff.ticket-detail') ? 'active' : '' }}" href="{{ route('staff.tickets') }}">
            <span class="sidebar-icon">▤</span>
            <span>Tickets</span>
        </a>

        <a class="sidebar-link {{ request()->routeIs('staff.knowledge-base') ? 'active' : '' }}" href="{{ route('staff.knowledge-base') }}">
            <span class="sidebar-icon">❔</span>
            <span>Knowledge Base</span>
        </a>

        <a class="sidebar-link {{ request()->routeIs('staff.reports') ? 'active' : '' }}" href="{{ route('staff.reports') }}">
            <span class="sidebar-icon">▧</span>
            <span>Reports</span>
        </a>

        <a class="sidebar-link {{ request()->routeIs('staff.notifications') ? 'active' : '' }}" href="{{ route('staff.notifications') }}">
            <span class="sidebar-icon">◌</span>
            <span>Notifications</span>
        </a>

        @if (auth()->user()->isAdmin())
            <span class="sidebar-section-label">Administration</span>

            <a class="sidebar-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">
                <span class="sidebar-icon">◌</span>
                <span>User Access</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.categories') ? 'active' : '' }}" href="{{ route('admin.categories') }}">
                <span class="sidebar-icon">▥</span>
                <span>Categories</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.sla-settings') ? 'active' : '' }}" href="{{ route('admin.sla-settings') }}">
                <span class="sidebar-icon">◷</span>
                <span>SLA Settings</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}" href="{{ route('admin.audit-logs') }}">
                <span class="sidebar-icon">▦</span>
                <span>Audit Logs</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.system-settings') ? 'active' : '' }}" href="{{ route('admin.system-settings') }}">
                <span class="sidebar-icon">⚙</span>
                <span>System Settings</span>
            </a>
        @endif
    </nav>

    <div class="sidebar-footer">
        <div class="user-mini">
            <div class="user-mini-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div>
                <strong>{{ auth()->user()->name }}</strong>
                <small>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Helpdesk Staff' }}</small>
            </div>
            <form class="sidebar-logout" action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit" aria-label="Sign out" title="Sign out">↗</button>
            </form>
        </div>
    </div>
</aside>