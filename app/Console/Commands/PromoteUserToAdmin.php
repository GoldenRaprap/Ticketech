<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Signature('users:promote-admin {email : Email address of the active, verified account to promote}')]
#[Description('Grant administrator access to an existing verified account')]
class PromoteUserToAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user === null) {
            $this->error('No account was found for that email address.');

            return self::FAILURE;
        }

        if ($user->role === 'admin') {
            $this->info("{$user->email} already has administrator access.");

            return self::SUCCESS;
        }

        if (! $user->hasVerifiedEmail() || ! $user->isActive()) {
            $this->error('Only an active account with a verified email can be promoted.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($user): ?User {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->role === 'admin') {
                return $lockedUser;
            }

            if (! $lockedUser->hasVerifiedEmail() || ! $lockedUser->isActive()) {
                return null;
            }

            $lockedUser->role = 'admin';
            $lockedUser->staff_approval_status = 'approved';
            $lockedUser->save();

            return $lockedUser;
        });

        if ($user === null) {
            $this->error('Only an active account with a verified email can be promoted.');

            return self::FAILURE;
        }

        Log::notice('Administrator access granted from the command line.', [
            'user_id' => $user->id,
        ]);
        $this->info("{$user->email} now has administrator access.");

        return self::SUCCESS;
    }
}
