<?php

namespace App\Models\Eloquent\Scopes;

use App\Models\Staff;
use App\Services\DepartmentPermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TicketAccessibleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $staff = Auth::guard('staff')->user();

        if (! $staff instanceof Staff) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $departmentPermissions = app(DepartmentPermissionService::class);

        if ($departmentPermissions->canAccessAllDepartments($staff)) {
            return;
        }

        $deptIds = $departmentPermissions->getAccessibleDepartmentIds($staff);

        if ($deptIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereIn($model->qualifyColumn('dept_id'), $deptIds);
    }
}
