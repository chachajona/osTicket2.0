<?php

namespace App\Models;

use Spatie\Permission\Models\Role;

class LegacyRole extends Role
{
    public function getConnectionName(): ?string
    {
        return config('permission.connection', 'legacy');
    }
}
