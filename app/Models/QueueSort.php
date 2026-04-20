<?php

namespace App\Models;

/**
 * QueueSort model for the legacy osTicket ost_queue_sort table.
 *
 * @property int $id
 * @property int $queue_id
 * @property string $flags
 * @property string $name
 * @property string $config
 * @property-read Queue|null $queue
 */
class QueueSort extends LegacyModel
{
    protected $table = 'queue_sort';

    protected $primaryKey = 'id';

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'queue_id', 'id');
    }
}
