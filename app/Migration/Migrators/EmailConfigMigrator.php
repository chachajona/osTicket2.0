<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class EmailConfigMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [
            [
                'name' => 'email',
                'source' => 'email',
                'target' => 'email',
                'primary_key' => 'email_id',
                'unique_by' => ['email_id'],
            ],
            [
                'name' => 'email_account',
                'source' => 'email_account',
                'target' => 'email_account',
                'primary_key' => 'id',
                'unique_by' => ['id'],
            ],
            [
                'name' => 'email_template_group',
                'source' => 'email_template_group',
                'target' => 'email_template_group',
                'primary_key' => 'tpl_id',
                'unique_by' => ['tpl_id'],
            ],
            [
                'name' => 'email_template',
                'source' => 'email_template',
                'target' => 'email_template',
                'primary_key' => 'id',
                'unique_by' => ['id'],
            ],
        ];
    }
}
