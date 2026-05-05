<?php

namespace App\Models;

use Database\Factories\QueueFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Queue model for the legacy osTicket ost_queue table.
 *
 * @property int $id
 * @property int $parent_id
 * @property int $staff_id
 * @property string $flags
 * @property string $title
 * @property string $config
 * @property string $created
 * @property string $updated
 * @property-read Queue|null $parent
 * @property-read Collection<int, Queue> $children
 * @property-read Collection<int, QueueColumn> $columns
 * @property-read Collection<int, QueueSort> $sorts
 */
class Queue extends LegacyModel
{
    /** @use HasFactory<QueueFactory> */
    use HasFactory;

    protected $table = 'queue';

    protected $primaryKey = 'id';

    public function parent()
    {
        return $this->belongsTo(Queue::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(Queue::class, 'parent_id', 'id');
    }

    public function columns()
    {
        return $this->hasMany(QueueColumn::class, 'queue_id', 'id');
    }

    public function sorts()
    {
        return $this->hasMany(QueueSort::class, 'queue_id', 'id');
    }
}
