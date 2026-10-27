<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isStaff(), 403);

        if ($user->staff_approval_status === 'denied') {
            return to_route('staff.application.denied');
        }

        if ($user->hasVerifiedEmail()) {
            return to_route($this->destinationRoute($user));
        }

        return view('helpdesk.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        if ($request->user()->isAdmin()) {
            return to_route('admin.dashboard');
        }

        if ($request->user()->staff_approval_status === 'denied') {
            return to_route('staff.application.denied');
        }

        return to_route('staff.approval.pending')->with(
            'status',
            'Your email is verified. Your registration is waiting for administrator approval.',
        );
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isStaff(), 403);

        if ($user->staff_approval_status === 'denied') {
            return to_route('staff.application.denied');
        }

        if ($user->hasVerifiedEmail()) {
            return to_route($this->destinationRoute($user));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent to your email address.');
    }

    private function destinationRoute(User $user): string
    {
        if ($user->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($user->staff_approval_status === 'denied') {
            return 'staff.application.denied';
        }

        return $user->isStaffApproved() ? 'staff.dashboard' : 'staff.approval.pending';
    }
}
