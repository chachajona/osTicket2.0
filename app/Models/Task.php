<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Task model for the legacy osTicket ost_task table.
 *
 * Tasks are polymorphic objects similar to tickets; object_type='T' for tickets, 'A' for tasks.
 *
 * @property int $id
 * @property int $object_id
 * @property string $object_type
 * @property string $number
 * @property int $dept_id
 * @property int $staff_id
 * @property int $team_id
 * @property int $lock_id
 * @property int $flags
 * @property string $duedate
 * @property string $closed
 * @property string $created
 * @property string $updated
 * @property-read Staff|null      $staff
 * @property-read Department|null $department
 * @property-read Team|null       $team
 * @property-read Thread|null     $thread
 * @property-read TaskCdata|null  $cdata
 */
class Task extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Get the staff member assigned to this task.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    /**
     * Get the department the task belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'id');
    }

    /**
     * Get the team assigned to this task.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    /**
     * Get the thread associated with the task.
     *
     * osTicket uses object_type='A' to distinguish task threads from ticket threads.
     *
     * @return HasOne<Thread, $this>
     */
    public function thread()
    {
        return $this->hasOne(Thread::class, 'object_id', 'id')
            ->where('object_type', 'A');
    }

    /**
     * Get the task's custom field data from ost_task__cdata.
     *
     * @return HasOne<TaskCdata, $this>
     */
    public function cdata()
    {
        return $this->hasOne(TaskCdata::class, 'task_id', 'id');
    }
}
