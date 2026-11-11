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

        if (blank($email) && blank($password)) {
            if (app()->isProduction()) {
                throw new RuntimeException('Set HELPDESK_STAFF_EMAIL and HELPDESK_STAFF_PASSWORD to seed the first admin.');
            }

            return;
        }

        if (! is_string($email)
            || ! is_string($password)
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
            || mb_strlen($password) < 12) {
            throw new RuntimeException('HELPDESK_STAFF_EMAIL must be valid and HELPDESK_STAFF_PASSWORD must be at least 12 characters.');
        }

        $email = mb_strtolower(trim($email));

        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('The configured helpdesk admin email belongs to an existing non-admin account.');
        }

        $admin = new User([
            'name' => config('services.helpdesk_staff.name') ?: 'PCU Helpdesk Admin',
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
        ]);
        $admin->email_verified_at = now();
        $admin->save();
    }
}
