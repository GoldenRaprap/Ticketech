<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (Auth::user()->isStaff()) {
            return redirect()->intended(route('staff.dashboard'));
        }

        $request->session()->forget('url.intended');

        return to_route('home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
