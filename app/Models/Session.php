<?php

namespace App\Models;

/**
 * Session model for the legacy osTicket ost_session table.
 * Uses a string primary key: session_id (varchar 255).
 *
 * @property string $session_id
 * @property int $staff_id
 * @property string $session_data
 * @property string $session_expire
 * @property-read Staff|null $staff
 */
class Session extends LegacyModel
{
    protected $table = 'session';

    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
