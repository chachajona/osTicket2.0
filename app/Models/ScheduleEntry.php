<?php

namespace App\Models;

/**
 * ScheduleEntry model for the legacy osTicket ost_schedule_entry table.
 *
 * @property int $id
 * @property int $schedule_id
 * @property int $flags
 * @property int $sort
 * @property string $name
 * @property string $repeats
 * @property string $starts_on
 * @property string $starts_at
 * @property string $ends_on
 * @property string $ends_at
 * @property string $stops_on
 * @property int $day
 * @property int $week
 * @property int $month
 * @property string $created
 * @property string $updated
 * @property-read Schedule $schedule
 */
class ScheduleEntry extends LegacyModel
{
    protected $table = 'schedule_entry';

    protected $primaryKey = 'id';

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'id');
    }
}
