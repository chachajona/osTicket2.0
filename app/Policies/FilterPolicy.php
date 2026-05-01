<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Filter;
use App\Models\Staff;

class FilterPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->canViewFilters($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.filter.create');
    }

    public function update(Staff $staff, Filter $filter): bool
    {
        return $filter instanceof Filter && $staff->hasPermissionTo('admin.filter.update');
    }

    public function delete(Staff $staff, Filter $filter): bool
    {
        return $filter instanceof Filter && $staff->hasPermissionTo('admin.filter.delete');
    }

    private function canViewFilters(Staff $staff): bool
    {
        return $staff->hasPermissionTo('admin.filter.create')
            || $staff->hasPermissionTo('admin.filter.update')
            || $staff->hasPermissionTo('admin.filter.delete');
    }
}
