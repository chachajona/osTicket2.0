<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;
use App\Models\Ticket;
use App\Services\DepartmentPermissionService;

final class TicketActionPolicy
{
    public function __construct(
        private readonly DepartmentPermissionService $deptService,
    ) {}

    public function postNote(Staff $staff, Ticket $ticket): bool
    {
        return $staff->can('tickets.post-note') && $this->deptService->hasAccessToDepartment($staff, $ticket->dept_id);
    }

    public function assign(Staff $staff, Ticket $ticket): bool
    {
        return $staff->can('tickets.assign') && $this->deptService->hasAccessToDepartment($staff, $ticket->dept_id);
    }

    public function setStatus(Staff $staff, Ticket $ticket): bool
    {
        return $staff->can('tickets.set-status') && $this->deptService->hasAccessToDepartment($staff, $ticket->dept_id);
    }
}
