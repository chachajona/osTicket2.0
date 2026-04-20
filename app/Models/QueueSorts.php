<?php

namespace App\Models;

/**
 * QueueSorts pivot model for the legacy osTicket ost_queue_sorts table.
 * Composite primary key: (queue_id, sort_id).
 *
 * @property int $queue_id
 * @property int $sort_id
 * @property int $staff_id
 * @property int $sort
 * @property-read Queue|null $queue
 * @property-read QueueSort|null $queueSort
 */
class QueueSorts extends LegacyModel
{
    protected $table = 'queue_sorts';

    public $incrementing = false;

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'queue_id', 'id');
    }

    public function queueSort()
    {
        return $this->belongsTo(QueueSort::class, 'sort_id', 'id');
    }
}
