<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\StaffRegistrationBlock;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffRegistrationController extends Controller
{
    public function create(): View
    {
        return view('helpdesk.register', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (StaffRegistrationBlock::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('cleared_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email address is blocked from staff registration. Contact an administrator if you believe this is a mistake.',
            ]);
        }

        $user = new User([
            'name' => $validated['name'],
            'email' => $email,
            'password' => $validated['password'],
            'role' => 'staff',
            'department_id' => $validated['department_id'] ?? null,
        ]);
        $user->staff_approval_status = 'pending';
        $user->save();

        $user->sendEmailVerificationNotification();
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('verification.notice')->with(
            'status',
            'We sent a verification link to your email address. Verify it to continue to the approval queue.',
        );
    }

    public function approvalPending(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isStaff(), 403);

        if ($user->staff_approval_status === 'denied') {
            return to_route('staff.application.denied');
        }

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors([
                'email' => 'This account is inactive. Contact an administrator for assistance.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        if ($user->isStaffApproved()) {
            return to_route('staff.dashboard');
        }

        return view('helpdesk.approval-pending');
    }

    public function applicationDenied(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isStaff(), 403);

        if ($user->staff_approval_status !== 'denied') {
            return to_route($user->isStaffApproved()
                ? ($user->isAdmin() ? 'admin.dashboard' : 'staff.dashboard')
                : ($user->hasVerifiedEmail() ? 'staff.approval.pending' : 'verification.notice'));
        }

        return view('helpdesk.access-denied');
    }
}
