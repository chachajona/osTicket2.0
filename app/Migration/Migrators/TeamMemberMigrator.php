<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class TeamMemberMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'team_member',
            'source' => 'team_member',
            'target' => 'team_member',
            'primary_key' => null,
            'unique_by' => ['team_id', 'staff_id'],
        ]];
    }
}
