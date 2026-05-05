<?php

namespace App\Services\Admin;

use App\Models\LegacyRole as Role;
use App\Models\Staff;
use App\Models\StaffDeptAccess;

class DepartmentRoleResolver
{
    public function roleForDepartment(Staff $staff, int $deptId): ?Role
    {
        $departmentAccess = StaffDeptAccess::query()
            ->where('staff_id', $staff->getKey())
            ->where('dept_id', $deptId)
            ->first();

        if ($departmentAccess) {
            $departmentRole = $this->resolveRole($departmentAccess->role_id);

            if ($departmentRole) {
                return $departmentRole;
            }
        }

        return $this->resolveRole($staff->role_id);
    }

    private function resolveRole(mixed $roleId): ?Role
    {
        $resolvedRoleId = filter_var($roleId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($resolvedRoleId === false) {
            return null;
        }

        return Role::query()->find($resolvedRoleId);
    }
}
