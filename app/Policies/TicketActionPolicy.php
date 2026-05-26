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
        return $this->canPerform($staff, $ticket, 'tickets.post-note');
    }

    public function postReply(Staff $staff, Ticket $ticket): bool
    {
        return $this->canPerform($staff, $ticket, 'tickets.post-reply');
    }

    public function assign(Staff $staff, Ticket $ticket): bool
    {
        return $this->canPerform($staff, $ticket, 'tickets.assign');
    }

    public function setStatus(Staff $staff, Ticket $ticket): bool
    {
        return $this->canPerform($staff, $ticket, 'tickets.set-status');
    }

    private function canPerform(Staff $staff, Ticket $ticket, string $permission): bool
    {
        if (! $this->deptService->hasAccessToDepartment($staff, $ticket->dept_id)) {
            return false;
        }

        return (bool) $staff->isadmin || $staff->can($permission);
    }
}
