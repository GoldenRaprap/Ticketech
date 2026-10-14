<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaffSessionController extends Controller
{
    public function create(): View
    {
        return view('staff.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not have staff access.',
            ])->onlyInput('email');
        }

        if (! $request->user()->isStaff()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'The provided credentials do not have staff access.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('staff.dashboard'));
    }
}
