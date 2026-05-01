<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;

class StaffDeptAccessMigrator extends AbstractMigrator
{
    protected function definitions(): array
    {
        return [[
            'name' => 'staff_dept_access',
            'source' => 'staff_dept_access',
            'target' => 'staff_dept_access',
            'primary_key' => null,
            'unique_by' => ['staff_id', 'dept_id'],
        ]];
    }
}
