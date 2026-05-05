<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class FilterActionMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'filter_action',
            'source' => 'filter_action',
            'target' => 'filter_action',
            'primary_key' => 'id',
            'unique_by' => ['id'],
        ]];
    }
}
