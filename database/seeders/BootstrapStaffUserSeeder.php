<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class BootstrapStaffUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::query()->where('role', 'admin')->exists()) {
            return;
        }

        $email = config('services.helpdesk_staff.email');
        $password = config('services.helpdesk_staff.password');

        if (! $email || ! $password) {
            if (app()->isProduction()) {
                throw new RuntimeException('Set HELPDESK_STAFF_EMAIL and HELPDESK_STAFF_PASSWORD to bootstrap the first admin.');
            }

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('The configured helpdesk admin email belongs to an existing non-admin account.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($password) < 12) {
            throw new RuntimeException('The helpdesk admin email must be valid and the password must be at least 12 characters.');
        }

        $admin = User::create([
            'name' => config('services.helpdesk_staff.name'),
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
        ]);
        $admin->email_verified_at = now();
        $admin->save();
    }
}
