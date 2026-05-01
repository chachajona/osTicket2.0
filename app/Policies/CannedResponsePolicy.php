<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CannedResponse;
use App\Models\Staff;

class CannedResponsePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->canViewCannedResponses($staff);
    }

    public function view(Staff $staff, CannedResponse $cannedResponse): bool
    {
        return $cannedResponse instanceof CannedResponse && $this->canViewCannedResponses($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.canned.create');
    }

    public function update(Staff $staff, CannedResponse $cannedResponse): bool
    {
        return $cannedResponse instanceof CannedResponse && $staff->hasPermissionTo('admin.canned.update');
    }

    public function delete(Staff $staff, CannedResponse $cannedResponse): bool
    {
        return $cannedResponse instanceof CannedResponse && $staff->hasPermissionTo('admin.canned.delete');
    }

    private function canViewCannedResponses(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.canned.create')
            || $staff->hasPermissionTo('admin.canned.update')
            || $staff->hasPermissionTo('admin.canned.delete');
    }
}
