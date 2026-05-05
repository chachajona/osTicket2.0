<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class TeamMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'team',
            'source' => 'team',
            'target' => 'team',
            'primary_key' => 'team_id',
            'unique_by' => ['team_id'],
        ]];
    }
}
