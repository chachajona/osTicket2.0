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

    /**
     * Determine whether a staff member can access every department without
     * enumeration. Callers MUST check this before calling
     * getAccessibleDepartmentIds() to avoid accidentally filtering a query
     * with an empty whereIn() clause for admins.
     */
    public function canAccessAllDepartments(Staff $staff): bool
    {
        return (bool) $staff->isadmin;
    }

    /**
     * Return the concrete list of department IDs the staff member has been
     * explicitly granted. Admins return an empty list because they are not
     * scoped to any particular departments; callers that need to filter a
     * query by department should branch on canAccessAllDepartments() first
     * and skip the whereIn() when it returns true.
     *
     * @return int[]
     */
    public function getAccessibleDepartmentIds(Staff $staff): array
    {
        if ($this->canAccessAllDepartments($staff)) {
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
