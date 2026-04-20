<?php

namespace App\Models;

/**
 * Lock model for the legacy osTicket ost_lock table.
 *
 * @property int $lock_id
 * @property string $object_type
 * @property int $object_id
 * @property int $staff_id
 * @property string $expire
 * @property-read Staff|null $staff
 */
class Lock extends LegacyModel
{
    protected $table = 'lock';

    protected $primaryKey = 'lock_id';

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
