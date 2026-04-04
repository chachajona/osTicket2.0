<?php

namespace App\Models;

/**
 * QueueExport model for the legacy osTicket ost_queue_export table.
 *
 * @property int $id
 * @property int $queue_id
 * @property string $name
 * @property string $config
 * @property-read Queue|null $queue
 */
class QueueExport extends LegacyModel
{
    protected $table = 'queue_export';

    protected $primaryKey = 'id';

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'queue_id', 'id');
    }
}
