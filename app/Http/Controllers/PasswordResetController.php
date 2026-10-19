<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('pages.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
            ->first();

        if ($user?->isStaff()) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'If an account exists for that email, a reset link has been sent.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('pages.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
            ->first();

        if (! $user?->isStaff()) {
            return back()->withErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ])->onlyInput('email');
        }

        $status = Password::reset(
            [...$validated, 'email' => $user->email],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return to_route('login')->with('status', 'Your password has been reset. You can now sign in.');
        }

        return back()->withErrors([
            'email' => 'This password reset link is invalid or has expired.',
        ])->onlyInput('email');
    }
}
