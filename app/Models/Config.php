<?php

namespace App\Models;

/**
 * Config model for the legacy osTicket ost_config table.
 *
 * @property int $id
 * @property string $namespace
 * @property string $key
 * @property string $value
 * @property string $updated
 */
class Config extends LegacyModel
{
    protected $table = 'config';

    protected $primaryKey = 'id';

    public function scopeNamespace($query, string $namespace)
    {
        return $query->where('namespace', $namespace);
    }
}
