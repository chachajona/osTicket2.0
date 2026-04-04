<?php

namespace App\Models;

/**
 * ThreadEvent model for the legacy osTicket ost_thread_event table.
 *
 * Records audit trail events (assignment, status change, etc.) on threads.
 *
 * @property int $id
 * @property int $thread_id
 * @property string $thread_type
 * @property int $event_id
 * @property int $staff_id
 * @property int $team_id
 * @property int $dept_id
 * @property int $topic_id
 * @property string $data
 * @property string $username
 * @property int $uid
 * @property string $uid_type
 * @property int $annulled
 * @property string $timestamp
 * @property-read Thread      $thread
 * @property-read Event|null  $event
 * @property-read Staff|null  $staff
 */
class ThreadEvent extends LegacyModel
{
    protected $table = 'thread_event';

    protected $primaryKey = 'id';

    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
