<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * Sla model for the legacy osTicket ost_sla table.
 *
 * @property int $id
 * @property int $schedule_id
 * @property int $flags
 * @property int $grace_period
 * @property string $name
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Schedule|null $schedule
 * @property-read Collection<int, Ticket> $tickets
 * @property-read Collection<int, Department> $departments
 */
class Sla extends LegacyModel
{
    protected $table = 'sla';

    protected $primaryKey = 'id';

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'sla_id', 'id');
    }
}
