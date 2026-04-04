<?php

namespace App\Models;

/**
 * QueueColumns pivot model for the legacy osTicket ost_queue_columns table.
 * Composite primary key: (queue_id, column_id, staff_id).
 *
 * @property int $queue_id
 * @property int $column_id
 * @property int $staff_id
 * @property int $sort
 * @property string $config
 * @property-read Queue|null $queue
 * @property-read QueueColumn|null $column
 * @property-read Staff|null $staff
 */
class QueueColumns extends LegacyModel
{
    protected $table = 'queue_columns';

    public $incrementing = false;

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'queue_id', 'id');
    }

    public function column()
    {
        return $this->belongsTo(QueueColumn::class, 'column_id', 'id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
