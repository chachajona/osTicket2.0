<?php

namespace App\Models;

/**
 * QueueConfig pivot model for the legacy osTicket ost_queue_config table.
 * Composite primary key: (queue_id, staff_id).
 *
 * @property int $queue_id
 * @property int $staff_id
 * @property string $config
 * @property-read Queue|null $queue
 * @property-read Staff|null $staff
 */
class QueueConfig extends LegacyModel
{
    protected $table = 'queue_config';

    public $incrementing = false;

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'queue_id', 'id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
