<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\Staff;

class DepartmentPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->canViewDepartments($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAdminPermission('admin.department.create');
    }

    public function update(Staff $staff, Department $department): bool
    {
        return $department instanceof Department && $staff->hasAdminPermission('admin.department.update');
    }

    public function delete(Staff $staff, Department $department): bool
    {
        return $department instanceof Department && $staff->hasAdminPermission('admin.department.delete');
    }

    private function canViewDepartments(Staff $staff): bool
    {
        return $staff->hasAdminPermission(
            'admin.department.create',
            'admin.department.update',
            'admin.department.delete',
        );
    }
}
