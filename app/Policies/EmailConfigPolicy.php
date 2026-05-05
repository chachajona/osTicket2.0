<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;

class EmailConfigPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAdminPermission(
            'admin.email.create',
            'admin.email.update',
            'admin.email.delete',
        );
    }

    public function view(Staff $staff, mixed $subject): bool
    {
        return $subject !== null && $this->viewAny($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAdminPermission('admin.email.create');
    }

    public function update(Staff $staff, mixed $subject): bool
    {
        return $subject !== null && $staff->hasAdminPermission('admin.email.update');
    }

    public function delete(Staff $staff, mixed $subject): bool
    {
        return $subject !== null && $staff->hasAdminPermission('admin.email.delete');
    }
}
