<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * Schedule model for the legacy osTicket ost_schedule table.
 *
 * @property int $id
 * @property int $flags
 * @property string $name
 * @property string $timezone
 * @property string $description
 * @property string $created
 * @property string $updated
 * @property-read Collection<int, ScheduleEntry> $entries
 */
class Schedule extends LegacyModel
{
    protected $table = 'schedule';

    protected $primaryKey = 'id';

    public function entries()
    {
        return $this->hasMany(ScheduleEntry::class, 'schedule_id', 'id');
    }
}
