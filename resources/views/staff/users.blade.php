@extends('layouts.staff')
@section('title', 'User Access')
@section('heading', 'User Access')
@section('content')
<div class="admin-console-page admin-user-access-page">
    <header class="admin-page-header">
        <div>
            <span class="admin-page-eyebrow">ADMINISTRATION / ACCESS CONTROL</span>
            <h1>User access</h1>
            <p>Assign staff to departments, grant Department Admin access, or promote active accounts to Super Admin for global administration.</p>
        </div>
        <span class="admin-page-context"><span aria-hidden="true">●</span> User directory</span>
    </header>

    @if (session('status'))
        <div class="workspace-alert workspace-alert--success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="workspace-alert workspace-alert--error" role="alert">{{ session('warning') }}</div>
    @endif

    <section class="admin-console-card admin-staff-bulk-card">
        <header class="admin-console-card-heading">
            <div>
                <span class="admin-page-eyebrow">STAFF ONBOARDING</span>
                <h2>Add staff accounts</h2>
                <p>Add multiple staff at once. Accounts are approved immediately; each person receives a password setup link and can sign in without registering or waiting for approval.</p>
            </div>
        </header>

        <form action="{{ route('admin.users.store') }}" method="post" data-staff-bulk-form data-max-entries="50">
            @csrf
            @foreach ($errors->get('staff') as $error)
                <p class="admin-staff-validation" role="alert">{{ $error }}</p>
            @endforeach

            @php
                $staffEntries = old('staff', [['name' => '', 'email' => '']]);
                $staffEntries = is_array($staffEntries) ? array_filter($staffEntries, 'is_array') : [];
                $staffEntries = $staffEntries === [] ? [['name' => '', 'email' => '']] : $staffEntries;
            @endphp
            <div class="admin-staff-bulk-list" data-staff-entry-list>
                @foreach ($staffEntries as $index => $staffEntry)
                    <fieldset class="admin-staff-bulk-entry" data-staff-entry>
                        <legend data-entry-legend>Staff member {{ $loop->iteration }}</legend>
                        <label class="admin-staff-field" for="new-staff-{{ $index }}-name">
                            <span>Full name</span>
                            <input id="new-staff-{{ $index }}-name" name="staff[{{ $index }}][name]" data-staff-field="name" value="{{ is_string($staffEntry['name'] ?? null) ? $staffEntry['name'] : '' }}" autocomplete="name" maxlength="255" required>
                            @foreach ($errors->get('staff.'.$index.'.name') as $error)
                                <span class="admin-staff-validation" role="alert">{{ $error }}</span>
                            @endforeach
                        </label>
                        <label class="admin-staff-field" for="new-staff-{{ $index }}-email">
                            <span>Work email</span>
                            <input id="new-staff-{{ $index }}-email" name="staff[{{ $index }}][email]" data-staff-field="email" type="email" value="{{ is_string($staffEntry['email'] ?? null) ? $staffEntry['email'] : '' }}" autocomplete="email" maxlength="255" required>
                            @foreach ($errors->get('staff.'.$index.'.email') as $error)
                                <span class="admin-staff-validation" role="alert">{{ $error }}</span>
                            @endforeach
                        </label>
                        @if (auth()->user()->isAdmin())
                            <label class="admin-staff-field" for="new-staff-{{ $index }}-department">
                                <span>Department</span>
                                <select id="new-staff-{{ $index }}-department" name="staff[{{ $index }}][department_id]" data-staff-field="department">
                                    <option value="">Unassigned</option>
                                    @foreach ($activeDepartments as $department)
                                        <option value="{{ $department->id }}" @selected((string) ($staffEntry['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @foreach ($errors->get('staff.'.$index.'.department_id') as $error)
                                    <span class="admin-staff-validation" role="alert">{{ $error }}</span>
                                @endforeach
                            </label>
                        @else
                            <div class="admin-staff-field">
                                <span>Department</span>
                                <strong>{{ auth()->user()->department?->name ?? 'Unassigned' }}</strong>
                            </div>
                        @endif
                        <button class="admin-console-button admin-console-button--danger" type="button" data-remove-staff-entry aria-label="Remove staff member {{ $loop->iteration }}">Remove</button>
                    </fieldset>
                @endforeach
            </div>

            <template data-staff-entry-template>
                <fieldset class="admin-staff-bulk-entry" data-staff-entry>
                    <legend data-entry-legend></legend>
                    <label class="admin-staff-field" for="new-staff-__INDEX__-name">
                        <span>Full name</span>
                        <input id="new-staff-__INDEX__-name" name="staff[__INDEX__][name]" data-staff-field="name" autocomplete="name" maxlength="255" required>
                    </label>
                    <label class="admin-staff-field" for="new-staff-__INDEX__-email">
                        <span>Work email</span>
                        <input id="new-staff-__INDEX__-email" name="staff[__INDEX__][email]" data-staff-field="email" type="email" autocomplete="email" maxlength="255" required>
                    </label>
                    @if (auth()->user()->isAdmin())
                        <label class="admin-staff-field" for="new-staff-__INDEX__-department">
                            <span>Department</span>
                            <select id="new-staff-__INDEX__-department" name="staff[__INDEX__][department_id]" data-staff-field="department">
                                <option value="">Unassigned</option>
                                @foreach ($activeDepartments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <div class="admin-staff-field">
                            <span>Department</span>
                            <strong>{{ auth()->user()->department?->name ?? 'Unassigned' }}</strong>
                        </div>
                    @endif
                    <button class="admin-console-button admin-console-button--danger" type="button" data-remove-staff-entry>Remove</button>
                </fieldset>
            </template>

            <div class="admin-staff-bulk-actions">
                <button class="admin-console-button admin-console-button--secondary" type="button" data-add-staff-entry>Add another staff member</button>
                <button class="admin-console-button" type="submit">Create accounts and send setup links</button>
            </div>
            <p class="admin-staff-bulk-feedback" aria-live="polite" data-staff-entry-feedback></p>
        </form>
    </section>

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
                        @if ($application->department_id === null || auth()->user()->isAdmin())
                            <form
                                class="admin-staff-directory-form"
                                action="{{ route('admin.users.update', $application) }}"
                                method="post"
                                data-auto-submit-on-change
                            >
                                @csrf
                                @method('put')
                                <input type="hidden" name="staff[{{ $application->id }}][name]" value="{{ $application->name }}">
                                <input type="hidden" name="staff[{{ $application->id }}][email]" value="{{ $application->email }}">
                                <label class="admin-staff-field" for="pending-staff-{{ $application->id }}-department">Department</label>
                                <select id="pending-staff-{{ $application->id }}-department" name="staff[{{ $application->id }}][department_id]">
                                    <option value="">Unassigned</option>
                                    @foreach ($departmentAssignmentOptions as $department)
                                        <option value="{{ $department->id }}" @selected((string) $application->department_id === (string) $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <p class="admin-staff-directory-department">{{ $application->department?->name ?? 'Unassigned' }}</p>
                        @endif
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

    @if ($deniedUnverifiedApplications->isNotEmpty())
        <section class="admin-console-card">
            <header class="admin-console-card-heading">
                <div>
                    <span class="admin-page-eyebrow">REJECTED REGISTRATIONS</span>
                    <h2>Denied unverified applications</h2>
                    <p>Remove a denied account that never verified its email to release the address for a new registration. Removal is permanent.</p>
                </div>
                <span class="admin-readonly-badge">{{ $deniedUnverifiedApplications->count() }} denied</span>
            </header>

            <div class="admin-staff-list">
                @foreach ($deniedUnverifiedApplications as $application)
                    <article class="admin-staff-card">
                        <header class="admin-staff-card-heading">
                            <div class="admin-user-cell">
                                <span class="admin-user-avatar">{{ strtoupper(substr($application->name, 0, 1)) }}</span>
                                <span><strong>{{ $application->name }}</strong><small>{{ $application->email }}</small></span>
                            </div>
                            <span class="admin-staff-status admin-staff-status--inactive">Denied · email unverified</span>
                        </header>
                        <div class="admin-staff-card-actions">
                            <form
                                action="{{ route('admin.users.denied-unverified-application.destroy', $application) }}"
                                method="post"
                                data-confirm-delete
                                data-confirm-title="Remove denied application?"
                                data-confirm-message="This permanently removes {{ $application->email }} from the system and allows that address to register again."
                            >
                                @csrf
                                @method('delete')
                                <button class="admin-console-button admin-console-button--danger" type="submit">
                                    Remove application and release email
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
            <div><span>Total department accounts</span><strong>{{ $staffCount }}</strong></div>
            <small>Accounts remain for ticket history</small>
        </article>
        <article class="admin-metric-card">
            <span class="admin-metric-icon admin-metric-icon--green" aria-hidden="true">✓</span>
            <div><span>Active department access</span><strong>{{ $activeCount }}</strong></div>
            <small>Can sign in to the helpdesk</small>
        </article>
        <article class="admin-metric-card">
            <span class="admin-metric-icon admin-metric-icon--amber" aria-hidden="true">◷</span>
            <div><span>Deactivated department accounts</span><strong>{{ $inactiveCount }}</strong></div>
            <small>Access is off; account history is retained</small>
        </article>
    </section>

    <section class="admin-console-card">
        <header class="admin-console-card-heading">
            <div>
                <span class="admin-page-eyebrow">STAFF AND ADMINISTRATORS</span>
                <h2>Manage Super Admins, department admins and staff</h2>
                <p>Department admins manage their own department accounts. Ticket access remains shared across departments.</p>
            </div>
            <span class="admin-readonly-badge">{{ $directoryUsers->total() }} matching accounts</span>
        </header>

        <form class="admin-console-toolbar admin-user-filter-toolbar" action="{{ route('admin.users') }}" method="get" data-auto-submit-on-change>
            <label class="admin-search-field">
                <span class="visually-hidden">Search users</span>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.3" stroke="currentColor" stroke-width="1.7"/><path d="m15.5 15.5 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email" aria-label="Search staff and administrators">
            </label>
            <label class="admin-filter-field">
                <span class="visually-hidden">Filter by account access</span>
                <select name="status" aria-label="Filter by account access">
                    <option value="">All statuses</option>
                    @foreach (['active' => 'Active', 'inactive' => 'Deactivated'] as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if (auth()->user()->isAdmin())
                <label class="admin-filter-field admin-user-filter-department">
                    <span class="visually-hidden">Filter by department</span>
                    <select name="department_id" aria-label="Filter by department">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if ($filters !== [])
                <a class="admin-console-clear" href="{{ route('admin.users') }}">Clear</a>
            @endif
        </form>

        <div class="admin-table-wrap admin-staff-directory-wrap">
            <table class="admin-console-table admin-staff-directory">
                <thead>
                    <tr>
                        <th scope="col">Account</th>
                        <th scope="col">Role</th>
                        <th scope="col">Account access</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($directoryUsers as $directoryUser)
                        <tr>
                            <td>
                                <div class="admin-user-cell admin-staff-directory-user">
                                    <span class="admin-user-avatar" aria-hidden="true">{{ strtoupper(substr($directoryUser->name, 0, 1)) }}</span>
                                    @if ($directoryUser->role === 'staff')
                                        <form id="staff-update-{{ $directoryUser->id }}" class="admin-staff-directory-form" action="{{ route('admin.users.update', $directoryUser) }}" method="post" @if (auth()->user()->isAdmin() || ($directoryUser->department_id === null && auth()->user()->isDepartmentAdmin())) data-auto-submit-on-change @endif>
                                            @csrf
                                            @method('put')
                                            <label class="visually-hidden" for="staff-{{ $directoryUser->id }}-name">Full name</label>
                                            <input id="staff-{{ $directoryUser->id }}-name" name="staff[{{ $directoryUser->id }}][name]" value="{{ old('staff.'.$directoryUser->id.'.name', $directoryUser->name) }}" autocomplete="name" maxlength="255" required>
                                            <label class="visually-hidden" for="staff-{{ $directoryUser->id }}-email">Work email</label>
                                            <input id="staff-{{ $directoryUser->id }}-email" name="staff[{{ $directoryUser->id }}][email]" type="email" value="{{ old('staff.'.$directoryUser->id.'.email', $directoryUser->email) }}" autocomplete="email" maxlength="255" required>
                                            @if (auth()->user()->isAdmin())
                                                <label class="visually-hidden" for="staff-{{ $directoryUser->id }}-department">Department</label>
                                                <select id="staff-{{ $directoryUser->id }}-department" name="staff[{{ $directoryUser->id }}][department_id]">
                                                    <option value="">Unassigned</option>
                                                    @foreach ($departments as $department)
                                                        <option value="{{ $department->id }}" @selected((string) old('staff.'.$directoryUser->id.'.department_id', $directoryUser->department_id) === (string) $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (Inactive)' }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif ($directoryUser->department_id === null)
                                                <label class="visually-hidden" for="staff-{{ $directoryUser->id }}-department">Assign {{ $directoryUser->name }} to your department</label>
                                                <select id="staff-{{ $directoryUser->id }}-department" name="staff[{{ $directoryUser->id }}][department_id]">
                                                    <option value="">Unassigned</option>
                                                    @foreach ($departmentAssignmentOptions as $department)
                                                        <option value="{{ $department->id }}" @selected((string) old('staff.'.$directoryUser->id.'.department_id', $directoryUser->department_id) === (string) $department->id)>{{ $department->name }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <small class="admin-staff-directory-department">{{ $directoryUser->department?->name ?? 'Unassigned' }}</small>
                                            @endif
                                            @php($staffErrors = $errors->getBag('default'))
                                            @if ($staffErrors->has('staff.'.$directoryUser->id.'.name') || $staffErrors->has('staff.'.$directoryUser->id.'.email') || $staffErrors->has('staff.'.$directoryUser->id.'.employment_status'))
                                                <div class="admin-staff-validation" role="alert">
                                                    @foreach (['name', 'email', 'employment_status'] as $field)
                                                        @foreach ($staffErrors->get('staff.'.$directoryUser->id.'.'.$field) as $error)
                                                            <span>{{ $error }}</span>
                                                        @endforeach
                                                    @endforeach
                                                </div>
                                            @endif
                                        </form>
                                    @else
                                        <span>
                                            <strong>{{ $directoryUser->name }}</strong>
                                            <small>{{ $directoryUser->email }}</small>
                                            <small class="admin-staff-directory-department">{{ $directoryUser->department?->name ?? 'Unassigned' }}</small>
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="admin-staff-role-cell">
                                    <span class="admin-role-badge {{ $directoryUser->role !== 'staff' ? 'admin-role-badge--admin' : '' }}">
                                        {{ match ($directoryUser->role) {
                                            'admin' => 'Super Admin',
                                            'department_admin' => 'Department Admin',
                                            default => 'Staff',
                                        } }}
                                    </span>
                                    <div class="admin-user-management-actions">
                                        @can('manage-user-roles')
                                            @if ($directoryUser->employment_status === 'active' && auth()->id() !== $directoryUser->id)
                                                <form action="{{ route('admin.users.role.update', $directoryUser) }}" method="post" data-role-change-form>
                                                    @csrf
                                                    @method('patch')
                                                    @if ($directoryUser->department_id !== null)
                                                        <input type="hidden" name="department_id" value="{{ $directoryUser->department_id }}">
                                                    @endif
                                                    <label class="visually-hidden" for="user-{{ $directoryUser->id }}-role">Change {{ $directoryUser->name }}'s role</label>
                                                    <select id="user-{{ $directoryUser->id }}-role" class="admin-role-change-select" name="role" aria-label="Change {{ $directoryUser->name }}'s role" data-role-change-select>
                                                        <option value="staff" @selected($directoryUser->role === 'staff')>Staff</option>
                                                        @if ($directoryUser->department_id !== null)
                                                            <option value="department_admin" @selected($directoryUser->role === 'department_admin')>Department Admin</option>
                                                        @endif
                                                        <option value="admin" @selected($directoryUser->role === 'admin')>Super Admin</option>
                                                    </select>
                                                </form>
                                            @elseif ($directoryUser->isAdmin() && auth()->id() === $directoryUser->id)
                                                <span class="admin-readonly-badge">Your account</span>
                                            @elseif ($directoryUser->employment_status !== 'active')
                                                <span class="admin-readonly-badge">Only active accounts can change roles</span>
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            </td>
                            <td>
                                @php($currentAccessStatus = $directoryUser->isActive() ? 'active' : 'inactive')
                                @php($isLastActiveSuperAdmin = $directoryUser->isAdmin() && $currentAccessStatus === 'active' && $activeSuperAdminCount <= 1)
                                @if ($directoryUser->role === 'staff')
                                    <span class="admin-staff-status admin-staff-status--{{ $currentAccessStatus }}">{{ $currentAccessStatus === 'active' ? 'Active' : 'Deactivated' }}</span>
                                    <form
                                        id="user-status-{{ $directoryUser->id }}"
                                        action="{{ route('admin.users.status.update', $directoryUser) }}"
                                        method="post"
                                        data-confirm-submit
                                        data-confirm-title="{{ $currentAccessStatus === 'active' ? (auth()->id() === $directoryUser->id ? 'Deactivate your Super Admin account?' : 'Deactivate this account?') : 'Reactivate this account?' }}"
                                        data-confirm-message="{{ $currentAccessStatus === 'active' ? (auth()->id() === $directoryUser->id ? 'You will be signed out immediately. This is only allowed when another active Super Admin remains.' : 'This account will lose sign-in access, and any assigned tickets will return to the shared queue.') : 'This account will be able to sign in again. Existing ticket assignments will not be restored automatically.' }}"
                                        data-confirm-button="{{ $currentAccessStatus === 'active' ? 'Deactivate account' : 'Reactivate account' }}"
                                        data-confirm-danger="{{ $currentAccessStatus === 'active' ? 'true' : 'false' }}"
                                    >
                                        @csrf
                                        @method('patch')
                                        <input type="hidden" name="employment_status" value="{{ $currentAccessStatus === 'active' ? 'inactive' : 'active' }}">
                                        @foreach ($errors->get('employment_status') as $error)
                                            <span class="admin-staff-validation" role="alert">{{ $error }}</span>
                                        @endforeach
                                    </form>
                                @elseif (auth()->user()->can('manage-user-roles'))
                                    <span class="admin-staff-status admin-staff-status--{{ $currentAccessStatus }}">{{ $currentAccessStatus === 'active' ? 'Active' : 'Deactivated' }}</span>
                                    <form
                                        id="user-status-{{ $directoryUser->id }}"
                                        action="{{ route('admin.users.status.update', $directoryUser) }}"
                                        method="post"
                                        data-confirm-submit
                                        data-confirm-title="{{ $currentAccessStatus === 'active' ? (auth()->id() === $directoryUser->id ? 'Deactivate your Super Admin account?' : 'Deactivate this account?') : 'Reactivate this account?' }}"
                                        data-confirm-message="{{ $currentAccessStatus === 'active' ? (auth()->id() === $directoryUser->id ? 'You will be signed out immediately. This is only allowed when another active Super Admin remains.' : 'This account will lose sign-in access, and any assigned tickets will return to the shared queue.') : 'This account will be able to sign in again. Existing ticket assignments will not be restored automatically.' }}"
                                        data-confirm-button="{{ $currentAccessStatus === 'active' ? 'Deactivate account' : 'Reactivate account' }}"
                                        data-confirm-danger="{{ $currentAccessStatus === 'active' ? 'true' : 'false' }}"
                                    >
                                        @csrf
                                        @method('patch')
                                        <input type="hidden" name="employment_status" value="{{ $currentAccessStatus === 'active' ? 'inactive' : 'active' }}">
                                        @foreach ($errors->get('employment_status') as $error)
                                            <span class="admin-staff-validation" role="alert">{{ $error }}</span>
                                        @endforeach
                                    </form>
                                @else
                                    <span class="admin-staff-status admin-staff-status--{{ $currentAccessStatus }}">{{ $currentAccessStatus === 'active' ? 'Active' : 'Deactivated' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($directoryUser->role === 'staff')
                                    <div class="admin-user-management-actions">
                                        <button
                                            class="admin-console-button {{ $currentAccessStatus === 'active' ? 'admin-console-button--danger' : '' }}"
                                            type="submit"
                                            form="user-status-{{ $directoryUser->id }}"
                                        >
                                            {{ $currentAccessStatus === 'active' ? 'Deactivate account' : 'Reactivate account' }}
                                        </button>
                                    </div>
                                @elseif (auth()->user()->can('manage-user-roles'))
                                    <button
                                        class="admin-console-button {{ $currentAccessStatus === 'active' ? 'admin-console-button--danger' : '' }}"
                                        type="submit"
                                        form="user-status-{{ $directoryUser->id }}"
                                        @disabled($isLastActiveSuperAdmin)
                                        @if ($isLastActiveSuperAdmin)
                                            title="The last active Super Admin cannot be deactivated. Activate another Super Admin first."
                                        @endif
                                    >
                                        {{ $currentAccessStatus === 'active'
                                            ? (auth()->id() === $directoryUser->id ? 'Deactivate my account' : 'Deactivate account')
                                            : 'Reactivate account' }}
                                    </button>
                                @else
                                    <span class="admin-staff-directory-unavailable" aria-label="Not available for administrator accounts">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="workspace-empty-state"><strong>No staff or administrator accounts found</strong><p>Adjust your filters or review pending staff applications.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="admin-console-card-footer">
            <span>Showing {{ $directoryUsers->count() }} of {{ $directoryUsers->total() }} approved staff and administrators</span>
            {{ $directoryUsers->links() }}
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
