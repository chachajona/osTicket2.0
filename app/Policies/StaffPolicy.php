<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;

class StaffPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAdminPermission(
            'admin.staff.create',
            'admin.staff.update',
            'admin.staff.delete',
        );
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAdminPermission('admin.staff.create');
    }

    public function update(Staff $staff, Staff $subject): bool
    {
        return $subject instanceof Staff && $staff->hasAdminPermission('admin.staff.update');
    }

    public function delete(Staff $staff, Staff $subject): bool
    {
        return $subject instanceof Staff && $staff->hasAdminPermission('admin.staff.delete');
    }
}
