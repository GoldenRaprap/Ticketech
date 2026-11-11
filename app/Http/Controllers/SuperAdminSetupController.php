<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperAdminSetupController extends Controller
{
    private const LOCK_SETTING = 'system.bootstrap.super_admin';

    public function create(): View|RedirectResponse
    {
        if (User::query()->where('role', 'admin')->exists()) {
            return to_route('login')->with('status', 'Super Admin setup is already complete. Please sign in.');
        }

        $this->configuredSetupKey();

        return view('helpdesk.super-admin-setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if (User::query()->where('role', 'admin')->exists()) {
            return to_route('login')->with('status', 'Super Admin setup is already complete. Please sign in.');
        }

        $setupKey = $this->configuredSetupKey();
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'setup_key' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except(['setup_key', 'password', 'password_confirmation']));
        }

        if (! hash_equals($setupKey, $validator->validated()['setup_key'])) {
            return back()
                ->withErrors(['setup_key' => 'The setup key is invalid.'])
                ->withInput($request->except(['setup_key', 'password', 'password_confirmation']));
        }

        $admin = DB::transaction(function () use ($validator): ?User {
            $now = now();

            DB::table('system_settings')->insertOrIgnore([
                'setting' => self::LOCK_SETTING,
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_settings')
                ->where('setting', self::LOCK_SETTING)
                ->lockForUpdate()
                ->first();

            if (User::query()->where('role', 'admin')->exists()) {
                return null;
            }

            $validated = $validator->validated();
            $admin = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'admin',
            ]);
            $admin->staff_approval_status = 'approved';
            $admin->save();

            return $admin;
        });

        if ($admin === null) {
            return to_route('login')->with('status', 'Super Admin setup is already complete. Please sign in.');
        }

        Auth::login($admin);
        $request->session()->regenerate();
        $admin->sendEmailVerificationNotification();

        return to_route('verification.notice')->with(
            'status',
            'Your Super Admin account was created. Verify your email address to access the system.',
        );
    }

    private function configuredSetupKey(): string
    {
        $setupKey = config('services.super_admin_setup_key');

        abort_unless(
            is_string($setupKey) && mb_strlen($setupKey) >= 32,
            503,
            'Initial Super Admin setup is unavailable. Configure SUPER_ADMIN_SETUP_KEY with at least 32 characters.',
        );

        return $setupKey;
    }
}
