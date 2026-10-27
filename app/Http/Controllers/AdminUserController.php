<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\AuditActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'retired', 'terminated'])],
        ]);

        $staff = User::query()
            ->where('role', 'staff')
            ->where('staff_approval_status', 'approved');

        $staffMembers = (clone $staff)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('employment_status', $status))
            ->orderByRaw("CASE employment_status WHEN 'active' THEN 0 WHEN 'retired' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('staff.users', [
            'staffMembers' => $staffMembers,
            'pendingStaffApplications' => User::query()
                ->where('role', 'staff')
                ->where('staff_approval_status', 'pending')
                ->orderBy('created_at')
                ->get(),
            'filters' => $filters,
            'activeCount' => (clone $staff)->where('employment_status', 'active')->count(),
            'retiredCount' => (clone $staff)->where('employment_status', 'retired')->count(),
            'terminatedCount' => (clone $staff)->where('employment_status', 'terminated')->count(),
            'staffCount' => (clone $staff)->count(),
        ]);
    }

    public function approve(Request $request, User $user, AuditActivityLogger $auditLogger): RedirectResponse
    {
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

    public function update(Request $request, User $user, AuditActivityLogger $auditLogger): RedirectResponse
    {
        abort_unless($user->role === 'staff', 404);

        $staffDetails = $request->validate([
            'staff.'.$user->id.'.name' => ['required', 'string', 'max:255'],
            'staff.'.$user->id.'.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'staff.'.$user->id.'.employment_status' => ['required', Rule::in(['active', 'retired', 'terminated'])],
        ]);

        $validated = $staffDetails['staff'][$user->id];
        $previousDetails = [
            'name' => $user->name,
            'email' => $user->email,
            'employment_status' => $user->employment_status,
        ];

        $deactivating = $validated['employment_status'] !== 'active' && $user->employment_status === 'active';

        DB::transaction(function () use ($request, $user, $validated, $deactivating, $previousDetails, $auditLogger): void {
            if ($deactivating) {
                $user->remember_token = Str::random(60);
            }

            $user->update($validated);
            $auditLogger->record(
                $request,
                'staff_account_updated',
                "Updated staff access for {$user->name}.",
                $user,
                ['before' => $previousDetails, 'after' => $validated],
            );

            if ($deactivating) {
                Ticket::query()
                    ->where('assigned_to_user_id', $user->id)
                    ->orderBy('id')
                    ->chunkById(100, function ($tickets) use ($request, $user, $validated): void {
                        foreach ($tickets as $ticket) {
                            $ticket->update(['assigned_to_user_id' => null]);
                            TicketEvent::create([
                                'ticket_id' => $ticket->id,
                                'actor_id' => $request->user()->id,
                                'event' => 'assignment_changed',
                                'description' => "Assignment removed because {$user->name} was marked {$validated['employment_status']}.",
                            ]);
                        }
                    });
            }
        });

        $message = $deactivating
            ? 'Staff access updated. Assigned tickets were returned to the shared queue.'
            : 'Staff access record updated.';

        return to_route('admin.users')->with('status', $message);
    }
}
