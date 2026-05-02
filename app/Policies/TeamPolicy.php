<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;
use App\Models\Team;

class TeamPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->canViewTeams($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAdminPermission('admin.team.create');
    }

    public function update(Staff $staff, Team $team): bool
    {
        return $team instanceof Team && $staff->hasAdminPermission('admin.team.update');
    }

    public function delete(Staff $staff, Team $team): bool
    {
        return $team instanceof Team && $staff->hasAdminPermission('admin.team.delete');
    }

    private function canViewTeams(Staff $staff): bool
    {
        return $staff->hasAdminPermission(
            'admin.team.create',
            'admin.team.update',
            'admin.team.delete',
        );
    }
}
