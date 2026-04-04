<?php

namespace App\Models;

/**
 * Draft model for the legacy osTicket ost_draft table.
 *
 * @property int $id
 * @property int $staff_id
 * @property string $namespace
 * @property string $body
 * @property string $created
 * @property string $updated
 * @property-read Staff|null $staff
 */
class Draft extends LegacyModel
{
    protected $table = 'draft';

    protected $primaryKey = 'id';

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
