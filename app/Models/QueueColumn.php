<?php

namespace App\Models;

/**
 * QueueColumn model for the legacy osTicket ost_queue_column table.
 *
 * @property int $id
 * @property int $queue_id
 * @property string $flags
 * @property string $name
 * @property string $primary
 * @property string $secondary
 * @property string $config
 * @property string $filter
 * @property string $truncate
 * @property-read Queue|null $queue
 */
class QueueColumn extends LegacyModel
{
    protected $table = 'queue_column';

    protected $primaryKey = 'id';

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'queue_id', 'id');
    }
}
