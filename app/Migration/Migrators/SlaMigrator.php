<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class SlaMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'sla',
            'source' => 'sla',
            'target' => 'sla',
            'primary_key' => 'id',
            'unique_by' => ['id'],
        ]];
    }
}
