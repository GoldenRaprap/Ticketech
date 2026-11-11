<?php

namespace App\Policies;

use App\Enums\UserPermission;
use App\Models\Department;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(UserPermission::ManageUserAccess);
    }

    public function createInDepartment(User $actor, Department $department): bool
    {
        return $actor->isAdmin()
            || ($actor->isDepartmentAdmin() && $actor->department_id === $department->id);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin()
            || (
                $actor->isDepartmentAdmin()
                && $target->role === 'staff'
                && $actor->department_id !== null
                && ($actor->department_id === $target->department_id || $target->department_id === null)
            );
    }

    public function approve(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function deny(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }
}
