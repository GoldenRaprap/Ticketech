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
                <span class="admin-page-eyebrow">NEW HIRE</span>
                <h2>Add a staff member</h2>
                <p>Staff will receive a secure password setup link by email. Administrators never set or share their passwords.</p>
            </div>
        </header>

        @if ($errors->has('name') || $errors->has('email'))
            <div class="workspace-alert workspace-alert--error" role="alert">
                <strong>Unable to add staff member:</strong>
                <ul>
                    @foreach ($errors->get('name') as $error)<li>{{ $error }}</li>@endforeach
                    @foreach ($errors->get('email') as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form class="admin-staff-create-form" action="{{ route('admin.users.store') }}" method="post">
            @csrf
            <label class="admin-staff-field" for="new-staff-name">
                <span>Full name</span>
                <input id="new-staff-name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required>
            </label>
            <label class="admin-staff-field" for="new-staff-email">
                <span>Work email</span>
                <input id="new-staff-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
            </label>
            <button class="admin-console-button" type="submit">Add staff member</button>
        </form>
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
                <div class="workspace-empty-state"><strong>No staff accounts found</strong><p>Add a staff member above or adjust your filters.</p></div>
            @endforelse
        </div>

        <footer class="admin-console-card-footer">
            <span>Showing {{ $staffMembers->count() }} of {{ $staffMembers->total() }} staff accounts</span>
            {{ $staffMembers->links() }}
        </footer>
    </section>
</div>
@endsection
