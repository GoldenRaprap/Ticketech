<?php

namespace App\Enums;

enum UserPermission: string
{
    case ManageUserAccess = 'manage-user-access';
    case ManageUserRoles = 'manage-user-roles';
    case ManageDepartmentTickets = 'manage-department-tickets';
}
