@extends('layouts.staff')
@section('title', 'User Access')
@section('heading', 'User Access')
@section('content')
<div class="admin-console-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / ACCESS CONTROL</span>
            <h1>Staff access</h1>
            <p>Add new hires, update staff details, and deactivate accounts when employment ends.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> Staff directory</span>
    </header>

    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="workspace-alert workspace-alert--error" role="alert">{{ session('warning') }}</div>
    @endif

    @if ($pendingStaffApplications->isNotEmpty())
        <section class="admin-console-card">
            <header class="admin-console-card-heading">
                <div>
                    <span class="admin-page-eyebrow">ACCESS REQUESTS</span>
                    <h2>Pending staff approvals</h2>
                    <p>Applicants must verify their email before approval. You can deny an application at any time.</p>
                </div>
                <span class="admin-readonly-badge">{{ $pendingStaffApplications->count() }} pending</span>
            </header>

            <div class="admin-staff-list">
                @foreach ($pendingStaffApplications as $application)
                    <article class="admin-staff-card">
                        <header class="admin-staff-card-heading">
                            <div class="admin-user-cell">
                                <span class="admin-user-avatar">{{ strtoupper(substr($application->name, 0, 1)) }}</span>
                                <span><strong>{{ $application->name }}</strong><small>{{ $application->email }}</small></span>
                            </div>
                            <span class="admin-staff-status admin-staff-status--pending">
                                {{ $application->hasVerifiedEmail() ? 'Email verified' : 'Email not verified' }}
                            </span>
                        </header>
                        <div class="admin-staff-card-actions">
                            <form action="{{ route('admin.users.approve', $application) }}" method="post">
                                @csrf
                                <button class="admin-console-button" type="submit" @disabled(! $application->hasVerifiedEmail())>
                                    Approve staff access
                                </button>
                            </form>
                            <form action="{{ route('admin.users.deny', $application) }}" method="post">
                                @csrf
                                <button class="admin-console-button admin-console-button--danger" type="submit">
                                    Deny application
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="admin-metric-grid" aria-label="Staff access summary">
        <article class="admin-metric-card">
            <span class="admin-metric-icon admin-metric-icon--blue" aria-hidden="true">◎</span>
            <div><span>Total staff accounts</span><strong>{{ $staffCount }}</strong></div>
            <small>Retained for ticket history</small>
        </article>
        <article class="admin-metric-card">
            <span class="admin-metric-icon admin-metric-icon--green" aria-hidden="true">✓</span>
            <div><span>Active access</span><strong>{{ $activeCount }}</strong></div>
            <small>Can sign in to the helpdesk</small>
        </article>
        <article class="admin-metric-card">
            <span class="admin-metric-icon admin-metric-icon--amber" aria-hidden="true">◷</span>
            <div><span>Departed staff</span><strong>{{ $retiredCount + $terminatedCount }}</strong></div>
            <small>{{ $retiredCount }} retired · {{ $terminatedCount }} terminated</small>
        </article>
    </section>

    <section class="admin-console-card">
        <header class="admin-console-card-heading">
            <div>
                <span class="admin-page-eyebrow">STAFF DIRECTORY</span>
                <h2>Manage staff access</h2>
                <p>Retired and terminated accounts cannot sign in. Their ticket history remains available to the helpdesk.</p>
            </div>
            <span class="admin-readonly-badge">{{ $staffMembers->total() }} matching accounts</span>
        </header>

        <form class="admin-console-toolbar" action="{{ route('admin.users') }}" method="get">
            <label class="admin-search-field">
                <span class="visually-hidden">Search staff</span>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.7"/><path d="m15.5 15.5 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email" aria-label="Search staff">
            </label>
            <label class="admin-filter-field">
                <span class="visually-hidden">Filter by employment status</span>
                <select name="status" aria-label="Filter by employment status">
                    <option value="">All statuses</option>
                    @foreach (['active' => 'Active', 'retired' => 'Retired', 'terminated' => 'Terminated'] as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="admin-console-button" type="submit">Apply filters</button>
            @if ($filters !== [])
                <a class="admin-console-clear" href="{{ route('admin.users') }}">Clear</a>
            @endif
        </form>

        <div class="admin-staff-list">
            @forelse ($staffMembers as $staffMember)
                <article class="admin-staff-card">
                    <header class="admin-staff-card-heading">
                        <div class="admin-user-cell">
                            <span class="admin-user-avatar">{{ strtoupper(substr($staffMember->name, 0, 1)) }}</span>
                            <span><strong>{{ $staffMember->name }}</strong><small>{{ $staffMember->email }}</small></span>
                        </div>
                        <span class="admin-staff-status admin-staff-status--{{ $staffMember->employment_status }}">{{ ucfirst($staffMember->employment_status) }}</span>
                    </header>

                    @php($staffErrors = $errors->getBag('default'))
                    @if ($staffErrors->has('staff.'.$staffMember->id.'.name') || $staffErrors->has('staff.'.$staffMember->id.'.email') || $staffErrors->has('staff.'.$staffMember->id.'.employment_status'))
                        <div class="admin-staff-validation" role="alert">
                            @foreach (['name', 'email', 'employment_status'] as $field)
                                @foreach ($staffErrors->get('staff.'.$staffMember->id.'.'.$field) as $error)
                                    <span>{{ $error }}</span>
                                @endforeach
                            @endforeach
                        </div>
                    @endif

                    <form class="admin-staff-edit-form" action="{{ route('admin.users.update', $staffMember) }}" method="post">
                        @csrf
                        @method('put')
                        <label class="admin-staff-field" for="staff-{{ $staffMember->id }}-name">
                            <span>Full name</span>
                            <input id="staff-{{ $staffMember->id }}-name" name="staff[{{ $staffMember->id }}][name]" value="{{ old('staff.'.$staffMember->id.'.name', $staffMember->name) }}" autocomplete="name" maxlength="255" required>
                        </label>
                        <label class="admin-staff-field" for="staff-{{ $staffMember->id }}-email">
                            <span>Work email</span>
                            <input id="staff-{{ $staffMember->id }}-email" name="staff[{{ $staffMember->id }}][email]" type="email" value="{{ old('staff.'.$staffMember->id.'.email', $staffMember->email) }}" autocomplete="email" maxlength="255" required>
                        </label>
                        <label class="admin-staff-field" for="staff-{{ $staffMember->id }}-status">
                            <span>Employment status</span>
                            <select id="staff-{{ $staffMember->id }}-status" name="staff[{{ $staffMember->id }}][employment_status]">
                                @foreach (['active' => 'Active', 'retired' => 'Retired', 'terminated' => 'Terminated'] as $status => $label)
                                    <option value="{{ $status }}" @selected(old('staff.'.$staffMember->id.'.employment_status', $staffMember->employment_status) === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="admin-console-button" type="submit">Save changes</button>
                    </form>
                </article>
            @empty
                <div class="workspace-empty-state"><strong>No staff accounts found</strong><p>Adjust your filters or review pending staff applications.</p></div>
            @endforelse
        </div>

        <footer class="admin-console-card-footer">
            <span>Showing {{ $staffMembers->count() }} of {{ $staffMembers->total() }} staff accounts</span>
            {{ $staffMembers->links() }}
        </footer>
    </section>

    @if ($pendingStaffApplications->isEmpty())
        <details class="admin-pending-empty">
            <summary>
                <span>Pending staff approvals</span>
                <span class="admin-readonly-badge">0 pending</span>
            </summary>
            <p>No pending staff applications. Verified requests will appear here for administrator review.</p>
        </details>
    @endif
</div>
@endsection
