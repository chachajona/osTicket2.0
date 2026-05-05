<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class FilterRuleMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'filter_rule',
            'source' => 'filter_rule',
            'target' => 'filter_rule',
            'primary_key' => 'id',
            'unique_by' => ['id'],
        ]];
    }
}
