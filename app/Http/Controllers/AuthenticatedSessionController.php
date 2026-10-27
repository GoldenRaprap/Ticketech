<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('helpdesk.login');
    }

    public function store(LoginRequest $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $auditLogger->record(
                $request,
                'login_failed',
                'Failed staff sign-in attempt.',
                properties: ['email' => $credentials['email']],
            );

            return back()->withErrors([
                'email' => 'These credentials do not have helpdesk staff access.',
            ])->onlyInput('email');
        }

        if (! $request->user()->isStaff() || ! $request->user()->isActive()) {
            $auditLogger->record(
                $request,
                'login_rejected',
                'Sign-in rejected because the account is not an active staff account.',
                $request->user(),
            );

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'These credentials do not have helpdesk staff access.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $auditLogger->record(
            $request,
            'login_succeeded',
            'Successful '.($request->user()->isAdmin() ? 'administrator' : 'staff').' sign-in.',
            $request->user(),
        );

        if ($request->user()->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($request->user()->staff_approval_status === 'denied') {
            return to_route('staff.application.denied');
        }

        if (! $request->user()->isStaffApproved()) {
            return to_route($request->user()->hasVerifiedEmail()
                ? 'staff.approval.pending'
                : 'verification.notice');
        }

        return redirect()->intended(route('staff.dashboard'));
    }

    public function preview(Request $request, string $role, AuditActivityLogger $auditLogger): RedirectResponse
    {
        abort_unless(config('app.env') === 'local' && in_array($role, ['staff', 'admin'], true), 404);

        $user = User::query()->updateOrCreate(
            ['email' => 'local-preview-'.$role.'@pcu.test'],
            [
                'name' => 'PCU Local Preview '.ucfirst($role),
                'password' => Str::random(64),
                'role' => $role,
            ],
        );

        Auth::login($user);
        $request->session()->regenerate();
        $auditLogger->record(
            $request,
            'login_succeeded',
            'Local preview sign-in as '.($user->isAdmin() ? 'administrator' : 'staff').'.',
            $user,
        );

        return to_route($role === 'admin' ? 'admin.dashboard' : 'staff.dashboard');
    }

    public function destroy(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $auditLogger->record($request, 'logout', 'Staff sign-out.');
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
