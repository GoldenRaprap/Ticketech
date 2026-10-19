<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('pages.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'These credentials do not have helpdesk staff access.',
            ])->onlyInput('email');
        }

        if (! $request->user()->isStaff()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'These credentials do not have helpdesk staff access.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        if ($request->user()->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('staff.dashboard'));
    }

    public function preview(Request $request, string $role): RedirectResponse
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

        return to_route($role === 'admin' ? 'admin.dashboard' : 'staff.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
