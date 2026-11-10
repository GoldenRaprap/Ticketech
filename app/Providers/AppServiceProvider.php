<?php

namespace App\Providers;

use App\Enums\UserPermission;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::define(
            UserPermission::ManageUserAccess->value,
            fn (User $user): bool => $user->hasPermissionTo(UserPermission::ManageUserAccess),
        );
        Gate::define(
            UserPermission::ManageUserRoles->value,
            fn (User $user): bool => $user->hasPermissionTo(UserPermission::ManageUserRoles),
        );
        Gate::define(
            UserPermission::ManageDepartmentTickets->value,
            fn (User $user): bool => $user->hasPermissionTo(UserPermission::ManageDepartmentTickets),
        );
    }
}
