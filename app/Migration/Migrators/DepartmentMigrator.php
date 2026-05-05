<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class DepartmentMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'department',
            'source' => 'department',
            'target' => 'department',
            'primary_key' => 'id',
            'unique_by' => ['id'],
        ]];
    }
}
