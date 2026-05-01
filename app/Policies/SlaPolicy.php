<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sla;
use App\Models\Staff;

class SlaPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->canViewSlas($staff);
    }

    public function view(Staff $staff, Sla $sla): bool
    {
        return $sla instanceof Sla && $this->canViewSlas($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.sla.create');
    }

    public function update(Staff $staff, Sla $sla): bool
    {
        return $sla instanceof Sla && $staff->hasPermissionTo('admin.sla.update');
    }

    public function delete(Staff $staff, Sla $sla): bool
    {
        return $sla instanceof Sla && $staff->hasPermissionTo('admin.sla.delete');
    }

    private function canViewSlas(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.sla.create')
            || $staff->hasPermissionTo('admin.sla.update')
            || $staff->hasPermissionTo('admin.sla.delete');
    }
}
