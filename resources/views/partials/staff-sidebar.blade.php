<aside class="staff-sidebar" aria-label="Staff navigation">
    <a class="brand" href="{{ route('staff.dashboard') }}"><span class="brand-mark">T</span><span>TICKETTECH</span></a>
    <div class="sidebar-section">Workspace</div>
    <a class="sidebar-link {{ request()->routeIs('staff.dashboard') ? 'active' : '' }}" href="{{ route('staff.dashboard') }}">▦ <span>Dashboard</span></a>
    <div class="sidebar-section">Tickets</div>
    <a class="sidebar-link {{ request()->routeIs('staff.tickets') ? 'active' : '' }}" href="{{ route('staff.tickets') }}">▤ <span>All Tickets</span></a>
    <a class="sidebar-link child" href="{{ route('staff.tickets') }}">New <span class="badge" style="margin-left:auto">12</span></a>
    <a class="sidebar-link child" href="{{ route('staff.tickets') }}">Assigned to Me</a>
    <a class="sidebar-link child" href="{{ route('staff.tickets') }}">In Progress</a>
    <a class="sidebar-link child" href="{{ route('staff.tickets') }}">Waiting for User</a>
    <a class="sidebar-link child" href="{{ route('staff.tickets') }}">Resolved</a>
    <a class="sidebar-link child" href="{{ route('staff.tickets') }}">Closed</a>
    <div class="sidebar-section">Resources</div>
    <a class="sidebar-link" href="{{ route('staff.knowledge-base') }}">▧ <span>Knowledge Base</span></a>
    <a class="sidebar-link" href="{{ route('staff.reports') }}">▥ <span>Reports</span></a>
    <a class="sidebar-link" href="{{ route('staff.notifications') }}">◉ <span>Notifications</span></a>
    <div class="sidebar-section">Administration</div>
    <a class="sidebar-link" href="{{ route('staff.users') }}">♙ <span>User Management</span></a>
    <a class="sidebar-link" href="{{ route('staff.categories') }}">☷ <span>Categories</span></a>
    <a class="sidebar-link" href="{{ route('staff.sla-settings') }}">◷ <span>SLA Settings</span></a>
    <a class="sidebar-link" href="{{ route('staff.audit-logs') }}">≡ <span>Audit Logs</span></a>
    <a class="sidebar-link" href="{{ route('staff.system-settings') }}">⚙ <span>System Settings</span></a>
    <div class="sidebar-spacer"></div>
    <a class="sidebar-link" href="{{ route('staff.profile') }}">◉ <span>Profile</span></a>
    <a class="sidebar-link" href="{{ route('home') }}">↗ <span>Public Help Desk</span></a>
</aside>