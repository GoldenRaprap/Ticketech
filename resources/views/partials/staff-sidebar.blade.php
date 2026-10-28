<aside id="staff-sidebar" class="staff-sidebar" aria-label="{{ auth()->user()->isAdmin() ? 'Administrator' : 'Staff' }} navigation">
    <div class="sidebar-top">
        <a class="sidebar-brand" href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('staff.dashboard') }}">
            <img src="{{ asset('images/TickeTech_Logo_Transparent.png') }}" alt="Ticketech Edtech Support System">
        </a>
        <button class="sidebar-toggle" type="button" aria-label="Expand sidebar" aria-controls="staff-sidebar" aria-expanded="false" data-sidebar-toggle>
            <svg data-sidebar-toggle-icon="menu" aria-hidden="true" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <svg data-sidebar-toggle-icon="arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                <path d="M9.5 4v16.25m6.75-11.5-3 3 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('staff.dashboard', 'admin.dashboard') ? 'active' : '' }}" href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('staff.dashboard') }}" title="Dashboard">
            <span class="sidebar-icon" aria-hidden="true">⌂</span>
            <span class="sidebar-link-label">Dashboard</span>
        </a>

        <a class="sidebar-link {{ request()->routeIs('staff.tickets', 'staff.ticket-detail') ? 'active' : '' }}" href="{{ route('staff.tickets') }}" title="Tickets">
            <span class="sidebar-icon" aria-hidden="true">
                <svg data-sidebar-icon="ticket" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 6.5A1.5 1.5 0 0 1 5.5 5h13A1.5 1.5 0 0 1 20 6.5V9a3 3 0 0 0 0 6v2.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5V15a3 3 0 0 0 0-6V6.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                    <path d="M9 8v1m0 3v1m0 3v1m4-8h3m-3 4h3m-3 4h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="sidebar-link-label">Tickets</span>
        </a>

        <a class="sidebar-link {{ request()->routeIs('staff.reports') ? 'active' : '' }}" href="{{ route('staff.reports') }}" title="Reports">
            <span class="sidebar-icon" aria-hidden="true">
                <svg data-sidebar-icon="reports" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.5 19.5v-7h4v7h-4Zm5.5 0V5.5h4v14h-4Zm5.5 0v-10h4v10h-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="sidebar-link-label">Reports</span>
        </a>

        <a class="sidebar-link {{ request()->routeIs('staff.notifications') ? 'active' : '' }}" href="{{ route('staff.notifications') }}" title="Notifications">
            <span class="sidebar-icon" aria-hidden="true">◌</span>
            <span class="sidebar-link-label">Notifications</span>
        </a>

        @unless (auth()->user()->isAdmin())
            <a class="sidebar-link {{ request()->routeIs('staff.profile') ? 'active' : '' }}" href="{{ route('staff.profile') }}" title="Profile">
                <span class="sidebar-icon" aria-hidden="true">
                    <svg data-sidebar-icon="profile" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="sidebar-link-label">Profile</span>
            </a>
        @endunless

        @if (auth()->user()->isAdmin())
            <span class="sidebar-section-label sidebar-link-label">Administration</span>

            <a class="sidebar-link {{ request()->routeIs('admin.knowledge-base*') ? 'active' : '' }}" href="{{ route('admin.knowledge-base') }}" title="Knowledge Base">
                <span class="sidebar-icon" aria-hidden="true">
                    <svg data-sidebar-icon="knowledge-base" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 7.5c-1.4-1.1-3.2-1.6-5.2-1.6-1 0-2 .1-2.8.4v12.1c.9-.3 1.8-.4 2.8-.4 2 0 3.8.5 5.2 1.6m0-12.1c1.4-1.1 3.2-1.6 5.2-1.6 1 0 2 .1 2.8.4v12.1c-.9-.3-1.8-.4-2.8-.4-2 0-3.8.5-5.2 1.6m0-12.1v12.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="sidebar-link-label">Knowledge Base</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}" title="User Access">
                <span class="sidebar-icon" aria-hidden="true">♙</span>
                <span class="sidebar-link-label">User Access</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.moderation*') ? 'active' : '' }}" href="{{ route('admin.moderation') }}" title="Client Moderation">
                <span class="sidebar-icon" aria-hidden="true">⚑</span>
                <span class="sidebar-link-label">Client Moderation</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.sla-settings') ? 'active' : '' }}" href="{{ route('admin.sla-settings') }}" title="SLA Settings">
                <span class="sidebar-icon" aria-hidden="true">◷</span>
                <span class="sidebar-link-label">SLA Settings</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}" href="{{ route('admin.audit-logs') }}" title="Audit Logs">
                <span class="sidebar-icon" aria-hidden="true">
                    <svg data-sidebar-icon="audit-logs" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 4.75h8.5L19 8.2v11.05H7V4.75Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                        <path d="M15 5v3.5h3.5M10 12h6m-6 3.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4.5 7.5v13h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sidebar-link-label">Audit Logs</span>
            </a>

            <a class="sidebar-link {{ request()->routeIs('admin.system-settings') ? 'active' : '' }}" href="{{ route('admin.system-settings') }}" title="System Settings">
                <span class="sidebar-icon" aria-hidden="true">⚙</span>
                <span class="sidebar-link-label">System Settings</span>
            </a>
        @endif
    </nav>

    <div class="sidebar-footer">
        <div class="user-mini">
            <a class="user-mini-profile {{ request()->routeIs('staff.profile') ? 'active' : '' }}" href="{{ route('staff.profile') }}" aria-label="View {{ auth()->user()->displayName() }}'s profile" title="Profile">
                <span class="user-mini-avatar">
                    @if (auth()->user()->profile_photo_path)
                        <img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt="">
                    @else
                        <span aria-hidden="true">{{ strtoupper(substr(auth()->user()->displayName(), 0, 1)) }}</span>
                    @endif
                </span>
                <span class="user-mini-details">
                    <strong>{{ auth()->user()->displayName() }}</strong>
                    <small>{{ auth()->user()->isAdmin() ? 'Admin Account' : 'Staff Account' }}</small>
                </span>
            </a>
            <form class="sidebar-logout" action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit" aria-label="Sign out" title="Sign out">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.5 4.5h4A1.5 1.5 0 0 1 19 6v12a1.5 1.5 0 0 1-1.5 1.5h-4M10 8l4 4-4 4m4-4H4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>