<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\AuditActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function store(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'staff' => ['required', 'array', 'min:1', 'max:50'],
            'staff.*' => ['required', 'array:name,email,department_id'],
            'staff.*.name' => ['required', 'string', 'max:255'],
            'staff.*.email' => ['required', 'string', 'email', 'max:255', 'distinct:ignore_case', Rule::unique('users', 'email')],
            'staff.*.department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);

        $users = DB::transaction(function () use ($validated, $request, $auditLogger): array {
            $users = [];

            foreach ($validated['staff'] as $details) {
                $departmentId = $request->user()->isDepartmentAdmin()
                    ? $request->user()->department_id
                    : ($details['department_id'] ?? null);

                if ($departmentId !== null) {
                    $department = Department::query()->findOrFail($departmentId);
                    Gate::authorize('createInDepartment', [User::class, $department]);
                } else {
                    abort_unless($request->user()->isAdmin(), 403);
                }

                $user = new User([
                    'name' => $details['name'],
                    'email' => $details['email'],
                    'password' => Str::random(64),
                    'role' => 'staff',
                    'department_id' => $departmentId,
                ]);
                $user->employment_status = 'active';
                $user->staff_approval_status = 'approved';
                $user->email_verified_at = null;
                $user->save();

                $auditLogger->record(
                    $request,
                    'staff_account_created',
                    "Created active staff account for {$user->name}.",
                    $user,
                    ['after' => ['department_id' => $departmentId]],
                );

                $users[] = $user;
            }

            return $users;
        });

        $failedSetupLinks = 0;

        foreach ($users as $user) {
            if (Password::sendResetLink(['email' => $user->email]) !== Password::RESET_LINK_SENT) {
                $failedSetupLinks++;
            }
        }

        $status = count($users).' staff account'.(count($users) === 1 ? '' : 's').' created and approved.';

        if ($failedSetupLinks > 0) {
            return to_route('admin.users')
                ->with('status', $status)
                ->with('warning', "{$failedSetupLinks} password setup link(s) could not be sent. Ask those staff members to use Forgot password on the sign-in page.");
        }

        return to_route('admin.users')->with('status', $status.' Password setup links have been emailed.');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);
        $departmentId = $request->user()->isAdmin()
            ? ($filters['department_id'] ?? null)
            : $request->user()->department_id;
        $activeSuperAdminCount = User::query()
            ->where('role', 'admin')
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->count();

        $staff = User::query()
            ->whereIn('role', ['staff', 'department_admin'])
            ->where('staff_approval_status', 'approved');
        if ($departmentId !== null) {
            $staff->where('department_id', $departmentId);
        }

        $directoryUsers = User::query()
            ->with('department')
            ->whereIn('role', ['staff', 'department_admin', 'admin'])
            ->where('staff_approval_status', 'approved')
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $status === 'active'
                ? $query->where('employment_status', 'active')
                : $query->where('employment_status', '<>', 'active'))
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'department_admin' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN employment_status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('staff.users', [
            'directoryUsers' => $directoryUsers,
            'pendingStaffApplications' => User::query()
                ->where('role', 'staff')
                ->where('staff_approval_status', 'pending')
                ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
                ->orderBy('created_at')
                ->get(),
            'deniedUnverifiedApplications' => User::query()
                ->where('role', 'staff')
                ->where('staff_approval_status', 'denied')
                ->whereNull('email_verified_at')
                ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
                ->orderBy('created_at')
                ->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'activeDepartments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $filters,
            'activeSuperAdminCount' => $activeSuperAdminCount,
            'activeCount' => (clone $staff)->where('employment_status', 'active')->count(),
            'inactiveCount' => (clone $staff)->where('employment_status', '<>', 'active')->count(),
            'staffCount' => (clone $staff)->count(),
        ]);
    }

    public function approve(Request $request, User $user, AuditActivityLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('approve', $user), 404);
        abort_unless($user->role === 'staff' && $user->staff_approval_status === 'pending', 404);

        if (! $user->hasVerifiedEmail()) {
            return to_route('admin.users')->with(
                'warning',
                'This staff member must verify their email before approval.',
            );
        }

        if (! $user->isActive()) {
            return to_route('admin.users')->with(
                'warning',
                'Inactive staff registrations cannot be approved.',
            );
        }

        $previousApprovalStatus = $user->staff_approval_status;
        $user->staff_approval_status = 'approved';
        $user->save();
        $auditLogger->record(
            $request,
            'staff_application_approved',
            "Approved staff access for {$user->name}.",
            $user,
            ['before' => ['staff_approval_status' => $previousApprovalStatus], 'after' => ['staff_approval_status' => 'approved']],
        );

        return to_route('admin.users')->with('status', $user->name.' has been approved for staff access.');
    }

    public function deny(Request $request, User $user, AuditActivityLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('deny', $user), 404);
        abort_unless($user->role === 'staff' && $user->staff_approval_status === 'pending', 404);

        $previousApprovalStatus = $user->staff_approval_status;
        $user->staff_approval_status = 'denied';
        $user->save();
        $auditLogger->record(
            $request,
            'staff_application_denied',
            "Denied staff access for {$user->name}.",
            $user,
            ['before' => ['staff_approval_status' => $previousApprovalStatus], 'after' => ['staff_approval_status' => 'denied']],
        );

        return to_route('admin.users')->with('status', $user->name.' has been denied staff access.');
    }

    public function removeDeniedUnverifiedApplication(
        Request $request,
        User $user,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('delete', $user)
                && $user->role === 'staff'
                && $user->staff_approval_status === 'denied'
                && ! $user->hasVerifiedEmail(),
            404,
        );

        DB::transaction(function () use ($request, $user, $auditLogger): void {
            $auditLogger->record(
                $request,
                'denied_unverified_staff_application_removed',
                "Removed denied unverified staff application for {$user->name} to release its email address.",
                $user,
            );

            $user->delete();
        });

        return to_route('admin.users')->with(
            'status',
            'The denied unverified application was removed. Its email address can now be used to register again.',
        );
    }

    public function update(Request $request, User $user, AuditActivityLogger $auditLogger): RedirectResponse
    {
        abort_unless($user->role === 'staff' && $request->user()->can('update', $user), 404);

        $staffDetails = $request->validate([
            'staff.'.$user->id.'.name' => ['required', 'string', 'max:255'],
            'staff.'.$user->id.'.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'staff.'.$user->id.'.department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);

        $validated = $staffDetails['staff'][$user->id];
        $validated['department_id'] = $request->user()->isAdmin()
            ? ($validated['department_id'] ?? $user->department_id)
            : $user->department_id;
        if ($validated['department_id'] !== null) {
            $department = Department::query()->findOrFail($validated['department_id']);
            abort_unless($request->user()->can('createInDepartment', $department), 404);
        }
        $previousDetails = [
            'name' => $user->name,
            'email' => $user->email,
            'employment_status' => $user->employment_status,
            'department_id' => $user->department_id,
        ];

        DB::transaction(function () use ($request, $user, $validated, $previousDetails, $auditLogger): void {
            $user->update($validated);
            $auditLogger->record(
                $request,
                'staff_account_updated',
                "Updated staff access for {$user->name}.",
                $user,
                ['before' => $previousDetails, 'after' => $validated],
            );
        });

        return to_route('admin.users')->with('status', 'Staff account details updated.');
    }

    public function updateAdministrativeStatus(
        Request $request,
        User $user,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        if ($user->role === 'staff') {
            Gate::authorize('update', $user);
        } else {
            Gate::authorize('changeRole', $user);
        }

        $validated = $request->validate([
            'employment_status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $result = DB::transaction(function () use ($request, $user, $validated, $auditLogger): string {
            $superAdmins = User::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'employment_status', 'staff_approval_status']);
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                in_array($target->role, ['admin', 'department_admin', 'staff'], true)
                    && $target->staff_approval_status === 'approved',
                404,
            );

            if ($target->employment_status === $validated['employment_status']) {
                return 'unchanged';
            }

            if (
                $target->role === 'admin'
                && $target->employment_status === 'active'
                && $validated['employment_status'] === 'inactive'
                && $superAdmins->where('employment_status', 'active')
                    ->where('staff_approval_status', 'approved')
                    ->count() <= 1
            ) {
                return 'last_super_admin';
            }

            $previousStatus = $target->employment_status;
            $deactivating = $validated['employment_status'] === 'inactive';

            if ($deactivating) {
                $target->remember_token = Str::random(60);
            }

            $target->employment_status = $validated['employment_status'];
            $target->save();
            $auditLogger->record(
                $request,
                'user_access_status_updated',
                "{$target->name}'s account was ".($deactivating ? 'deactivated' : 'reactivated').'.',
                $target,
                [
                    'before' => ['employment_status' => $previousStatus],
                    'after' => ['employment_status' => $validated['employment_status']],
                ],
            );

            if ($deactivating) {
                $this->returnAssignedTicketsToQueue($request, $target);
            }

            return 'updated';
        });

        if ($result === 'last_super_admin') {
            return to_route('admin.users')->with(
                'warning',
                'The last active Super Admin cannot be deactivated. Activate another Super Admin first.',
            );
        }

        if ($result === 'unchanged') {
            return to_route('admin.users')->with('status', 'That account already has the selected access status.');
        }

        if ($request->user()->is($user) && $validated['employment_status'] === 'inactive') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->with('status', 'Your Super Admin access has been deactivated.');
        }

        return to_route('admin.users')->with(
            'status',
            $user->name.' has been '.($validated['employment_status'] === 'inactive' ? 'deactivated' : 'reactivated').'.',
        );
    }

    private function returnAssignedTicketsToQueue(Request $request, User $user): void
    {
        Ticket::query()
            ->where('assigned_to_user_id', $user->id)
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($request, $user): void {
                foreach ($tickets as $ticket) {
                    $ticket->update(['assigned_to_user_id' => null]);
                    TicketEvent::create([
                        'ticket_id' => $ticket->id,
                        'actor_id' => $request->user()->id,
                        'event' => 'assignment_changed',
                        'description' => "Assignment removed because {$user->name}'s access was deactivated.",
                    ]);
                }
            });
    }

    public function updateRole(Request $request, User $user, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'department_admin', 'staff'])],
            'department_id' => [
                'nullable',
                'required_if:role,department_admin',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
        ]);
        $newRole = $validated['role'];

        Gate::authorize('changeRole', $user);

        if ($request->user()->is($user)) {
            return to_route('admin.users')->with('warning', 'You cannot change your own Super Admin role.');
        }

        $newDepartmentId = match ($newRole) {
            'admin' => null,
            'department_admin' => (int) $validated['department_id'],
            default => $user->department_id,
        };

        $result = DB::transaction(function () use ($request, $user, $newRole, $newDepartmentId, $auditLogger): string {
            $activeAdmins = User::query()
                ->where('role', 'admin')
                ->where('employment_status', 'active')
                ->where('staff_approval_status', 'approved')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                in_array($target->role, ['admin', 'department_admin', 'staff'], true)
                    && $target->employment_status === 'active'
                    && $target->staff_approval_status === 'approved',
                404,
            );

            if ($target->role === $newRole && $target->department_id === $newDepartmentId) {
                return 'unchanged';
            }

            if ($target->role === 'admin' && $newRole !== 'admin' && $activeAdmins->count() <= 1) {
                return 'last_admin';
            }

            $previousRole = $target->role;
            $previousDepartmentId = $target->department_id;
            $target->role = $newRole;
            $target->department_id = $newDepartmentId;

            if ($previousRole === 'admin') {
                $target->remember_token = Str::random(60);
            }

            $target->save();
            $auditLogger->record(
                $request,
                'user_role_updated',
                "Changed {$target->name}'s role from {$previousRole} to {$newRole}.",
                $target,
                [
                    'before' => ['role' => $previousRole, 'department_id' => $previousDepartmentId],
                    'after' => ['role' => $newRole, 'department_id' => $newDepartmentId],
                ],
            );

            return 'updated';
        });

        if ($result === 'last_admin') {
            return to_route('admin.users')->with(
                'warning',
                'The last active Super Admin cannot be demoted. Promote another active account first.',
            );
        }

        if ($result === 'unchanged') {
            return to_route('admin.users')->with('status', 'That account already has the selected role.');
        }

        $roleLabel = match ($newRole) {
            'admin' => 'a Super Admin',
            'department_admin' => 'a Department Admin',
            default => 'a staff member',
        };

        return to_route('admin.users')->with('status', $user->name.' is now '.$roleLabel.'.');
    }
}
