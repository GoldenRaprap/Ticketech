<?php

namespace App\Providers;

use App\Enums\UserPermission;
use App\Models\TicketMisuseReport;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
        View::composer('partials.staff-sidebar', function (ViewInstance $view): void {
            $user = auth()->user();
            $unreadNotificationCount = $user === null
                ? 0
                : WorkspaceNotification::query()
                    ->visibleTo($user)
                    ->whereNull('read_at')
                    ->count();
            $pendingStaffApplicationCount = 0;
            $pendingReportCount = 0;

            if ($user?->can('manage-user-access')) {
                $pendingStaffApplications = User::query()
                    ->where('role', 'staff')
                    ->where('staff_approval_status', 'pending');

                if (! $user->isAdmin()) {
                    $pendingStaffApplications->where(function ($query) use ($user): void {
                        $query->where('department_id', $user->department_id)
                            ->orWhereNull('department_id');
                    });
                }

                $pendingStaffApplicationCount = $pendingStaffApplications->count();
            }

            if ($user?->isAdmin()) {
                $pendingReportCount = TicketMisuseReport::query()
                    ->where('status', 'pending')
                    ->count();
            }

            $view->with([
                'sidebarUnreadNotificationCount' => $unreadNotificationCount,
                'sidebarPendingStaffApplicationCount' => $pendingStaffApplicationCount,
                'sidebarPendingReportCount' => $pendingReportCount,
            ]);
        });

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
