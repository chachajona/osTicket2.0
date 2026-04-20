<?php

namespace App\Models;

/**
 * Note model for the legacy osTicket ost_note table.
 *
 * @property int $id
 * @property string $object_type
 * @property int $object_id
 * @property int $staff_id
 * @property string $note
 * @property string $created
 * @property-read Staff|null $staff
 */
class Note extends LegacyModel
{
    protected $table = 'note';

    protected $primaryKey = 'id';

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
