<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HelpTopic;
use App\Models\Staff;

class HelpTopicPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $this->canViewHelpTopics($staff);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAdminPermission('admin.helptopic.create');
    }

    public function update(Staff $staff, HelpTopic $helpTopic): bool
    {
        return $helpTopic instanceof HelpTopic && $staff->hasAdminPermission('admin.helptopic.update');
    }

    public function delete(Staff $staff, HelpTopic $helpTopic): bool
    {
        return $helpTopic instanceof HelpTopic && $staff->hasAdminPermission('admin.helptopic.delete');
    }

    private function canViewHelpTopics(Staff $staff): bool
    {
        return $staff->hasAdminPermission(
            'admin.helptopic.create',
            'admin.helptopic.update',
            'admin.helptopic.delete',
        );
    }
}
