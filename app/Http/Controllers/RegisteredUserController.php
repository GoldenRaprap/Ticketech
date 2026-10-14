<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('pages.register');
    }

    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'password']),
            'role' => 'requester',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('home');
    }
}
