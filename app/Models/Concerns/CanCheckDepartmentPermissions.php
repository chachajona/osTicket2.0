<?php

namespace App\Models\Concerns;

use App\Models\Ticket;
use App\Services\DepartmentPermissionService;
use Illuminate\Support\Facades\Gate;

trait CanCheckDepartmentPermissions
{
    public function canForTicket(string $perm, Ticket $ticket): bool
    {
        return $this->canInDept($perm, (int) $ticket->dept_id);
    }

    public function canInDept(string $perm, int $deptId): bool
    {
        if (! app(DepartmentPermissionService::class)->hasAccessToDepartment($this, $deptId)) {
            return false;
        }

        return Gate::forUser($this)->check('can-in-department', [$perm, $deptId]);
    }
}
