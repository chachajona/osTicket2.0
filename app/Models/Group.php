<?php

namespace App\Models;

/**
 * Group model for the legacy osTicket ost_group table.
 *
 * @property int $id
 * @property int $role_id
 * @property int $flags
 * @property string $name
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Role|null $role
 */
class Group extends LegacyModel
{
    protected $table = 'group';

    protected $primaryKey = 'id';

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
}
