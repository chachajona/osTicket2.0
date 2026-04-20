<?php

namespace App\Models;

/**
 * TaskCdata model for the legacy osTicket ost_task__cdata table.
 *
 * @property int $task_id
 * @property string $title
 */
class TaskCdata extends LegacyModel
{
    protected $table = 'task__cdata';

    protected $primaryKey = 'task_id';

    public $incrementing = false;

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }
}
