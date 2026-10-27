<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStaffProfileRequest;
use App\Services\AuditActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class StaffProfileController extends Controller
{
    public function edit(): View
    {
        return view('staff.profile');
    }

    public function update(UpdateStaffProfileRequest $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $previousProfilePhotoPath = $user->profile_photo_path;
        $previousDetails = [
            'name' => $user->name,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
        $emailChanged = $user->email !== $request->validated('email');
        $user->name = $request->validated('name');
        $user->nickname = $request->validated('nickname');
        $user->email = $request->validated('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $profilePhotoPath = $request->file('avatar')->store('profile-photos', 'public');

            if ($profilePhotoPath === false) {
                throw new RuntimeException('Unable to store the uploaded profile photo.');
            }

            $user->profile_photo_path = $profilePhotoPath;
        }

        $user->save();

        if ($request->hasFile('avatar') && $previousProfilePhotoPath !== null) {
            Storage::disk('public')->delete($previousProfilePhotoPath);
        }

        $auditLogger->record(
            $request,
            'staff_profile_updated',
            'Updated staff profile details.',
            $user,
            [
                'before' => $previousDetails,
                'after' => [
                    'name' => $user->name,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'profile_photo_updated' => $request->hasFile('avatar'),
                ],
            ],
        );

        return to_route('staff.profile')->with('status', 'Profile updated successfully.');
    }
}
