<?php

namespace App\Models;

/**
 * Role model for the legacy osTicket ost_role table.
 *
 * @property int $id
 * @property int $flags
 * @property string $name
 * @property string $permissions
 * @property string $notes
 * @property string $created
 * @property string $updated
 */
class Role extends LegacyModel
{
    protected $table = 'role';

    protected $primaryKey = 'id';
}
