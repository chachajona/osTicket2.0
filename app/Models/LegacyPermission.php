<?php

namespace App\Models;

use Spatie\Permission\Models\Permission;

class LegacyPermission extends Permission
{
    public function getConnectionName(): ?string
    {
        return config('permission.connection', 'legacy');
    }
}
