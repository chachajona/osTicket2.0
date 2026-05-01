<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\Staff;

class RolePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->canViewRoles($staff);
    }

    public function view(Staff $staff, Role $role): bool
    {
        return $role instanceof Role && $this->canViewRoles($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.role.create');
    }

    public function update(Staff $staff, Role $role): bool
    {
        return $role instanceof Role && $staff->hasPermissionTo('admin.role.update');
    }

    public function delete(Staff $staff, Role $role): bool
    {
        return $role instanceof Role && $staff->hasPermissionTo('admin.role.delete');
    }

    private function canViewRoles(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.role.create')
            || $staff->hasPermissionTo('admin.role.update')
            || $staff->hasPermissionTo('admin.role.delete');
    }
}
