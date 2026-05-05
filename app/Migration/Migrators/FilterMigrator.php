<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class FilterMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'filter',
            'source' => 'filter',
            'target' => 'filter',
            'primary_key' => 'id',
            'unique_by' => ['id'],
        ]];
    }
}
