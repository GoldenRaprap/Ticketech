<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStaffProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StaffProfileController extends Controller
{
    public function edit(): View
    {
        return view('staff.profile');
    }

    public function update(UpdateStaffProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = $user->email !== $request->validated('email');
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('staff.profile')->with('status', 'Profile updated successfully.');
    }
}
