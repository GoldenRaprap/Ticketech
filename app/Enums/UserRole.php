<?php

namespace App\Enums;

enum UserRole: string
{
    case Requester = 'requester';
    case Staff = 'staff';
    case DepartmentAdmin = 'department_admin';
    case SuperAdmin = 'admin';

    /**
     * @return list<UserPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => UserPermission::cases(),
            self::DepartmentAdmin => [
                UserPermission::ManageUserAccess,
                UserPermission::ManageDepartmentTickets,
            ],
            default => [],
        };
    }

    public function hasPermission(UserPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
