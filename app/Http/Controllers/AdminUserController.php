<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
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

        $staffMembers = User::query()
            ->where('role', 'staff')
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

        $staff = User::query()->where('role', 'staff');

        return view('staff.users', [
            'staffMembers' => $staffMembers,
            'filters' => $filters,
            'activeCount' => (clone $staff)->where('employment_status', 'active')->count(),
            'retiredCount' => (clone $staff)->where('employment_status', 'retired')->count(),
            'terminatedCount' => (clone $staff)->where('employment_status', 'terminated')->count(),
            'staffCount' => (clone $staff)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $staff = User::create([
            ...$validated,
            'password' => Str::random(64),
            'role' => 'staff',
            'employment_status' => 'active',
        ]);

        $status = Password::sendResetLink(['email' => $staff->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return to_route('admin.users')->with('warning', 'The staff account was created, but the password setup email could not be sent. Ask the staff member to use Forgot Password.');
        }

        return to_route('admin.users')->with('status', 'Staff account created. A password setup link was sent to '.$staff->email.'.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'staff', 404);

        $staffDetails = $request->validate([
            'staff.'.$user->id.'.name' => ['required', 'string', 'max:255'],
            'staff.'.$user->id.'.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'staff.'.$user->id.'.employment_status' => ['required', Rule::in(['active', 'retired', 'terminated'])],
        ]);

        $validated = $staffDetails['staff'][$user->id];

        $deactivating = $validated['employment_status'] !== 'active' && $user->employment_status === 'active';

        DB::transaction(function () use ($request, $user, $validated, $deactivating): void {
            if ($deactivating) {
                $user->remember_token = Str::random(60);
            }

            $user->update($validated);

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
