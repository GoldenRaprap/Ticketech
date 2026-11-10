<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('app:bootstrap-super-admin {name? : Initial Super Admin name} {email? : Initial Super Admin email}')]
#[Description('Create the first Super Admin for a fresh installation')]
class BootstrapSuperAdmin extends Command
{
    public function handle(): int
    {
        if (User::query()->where('role', 'admin')->exists()) {
            $this->error('A Super Admin account already exists. Bootstrap is only available before the first Super Admin is created.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->argument('name') ?? $this->ask('Super Admin name')));
        $email = Str::lower(trim((string) ($this->argument('email') ?? $this->ask('Super Admin email'))));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $superAdmin = DB::transaction(function () use ($name, $email, $password): ?User {
            if (User::query()->where('role', 'admin')->lockForUpdate()->get(['id'])->isNotEmpty()) {
                return null;
            }

            $superAdmin = new User([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'employment_status' => 'active',
                'staff_approval_status' => 'approved',
            ]);
            $superAdmin->email_verified_at = now();
            $superAdmin->save();

            return $superAdmin;
        });

        if ($superAdmin === null) {
            $this->error('A Super Admin account was created while bootstrap was running. No account was added.');

            return self::FAILURE;
        }

        Log::notice('Initial Super Admin account created from the command line.', [
            'user_id' => $superAdmin->id,
        ]);
        $this->info("{$superAdmin->email} is now the initial Super Admin.");

        return self::SUCCESS;
    }
}
