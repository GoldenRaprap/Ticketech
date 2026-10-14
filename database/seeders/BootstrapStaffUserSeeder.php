<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class BootstrapStaffUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('services.helpdesk_staff.email');
        $password = config('services.helpdesk_staff.password');

        if (! $email || ! $password) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('services.helpdesk_staff.name'),
                'password' => $password,
                'role' => 'admin',
            ],
        );
    }
}
