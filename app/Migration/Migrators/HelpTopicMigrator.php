<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class HelpTopicMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'help_topic',
            'source' => 'help_topic',
            'target' => 'help_topic',
            'primary_key' => 'topic_id',
            'unique_by' => ['topic_id'],
        ]];
    }
}
