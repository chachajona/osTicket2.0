<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffDeptAccess;

class DepartmentPermissionService
{
    public function hasAccessToDepartment(Staff $staff, int $deptId): bool
    {
        if ($staff->isadmin) {
            return true;
        }

        if ((int) $staff->dept_id === $deptId) {
            return true;
        }

        return StaffDeptAccess::where('staff_id', $staff->staff_id)
            ->where('dept_id', $deptId)
            ->exists();
    }

    public function getRoleIdForDepartment(Staff $staff, int $deptId): ?int
    {
        if ($staff->isadmin) {
            return null;
        }

        $access = StaffDeptAccess::where('staff_id', $staff->staff_id)
            ->where('dept_id', $deptId)
            ->first();

        return $access?->role_id;
    }

    public function getAccessibleDepartmentIds(Staff $staff): array
    {
        if ($staff->isadmin) {
            return [];
        }

        $deptIds = StaffDeptAccess::where('staff_id', $staff->staff_id)
            ->pluck('dept_id')
            ->map(static fn ($deptId): int => (int) $deptId)
            ->unique()
            ->values()
            ->all();

        if ($staff->dept_id && ! in_array((int) $staff->dept_id, $deptIds, true)) {
            array_unshift($deptIds, (int) $staff->dept_id);
        }

        return $deptIds;
    }

    public function hasRoleInDepartment(Staff $staff, int $deptId, int $roleId): bool
    {
        if ($staff->isadmin) {
            return true;
        }

        return StaffDeptAccess::where('staff_id', $staff->staff_id)
            ->where('dept_id', $deptId)
            ->where('role_id', $roleId)
            ->exists();
    }
}
