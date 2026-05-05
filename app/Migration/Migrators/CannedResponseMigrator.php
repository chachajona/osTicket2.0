<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class CannedResponseMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'canned_response',
            'source' => 'canned_response',
            'target' => 'canned_response',
            'primary_key' => 'canned_id',
            'unique_by' => ['canned_id'],
        ]];
    }
}
