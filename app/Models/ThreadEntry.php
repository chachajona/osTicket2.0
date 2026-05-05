<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ThreadEntry model for the legacy osTicket ost_thread_entry table.
 *
 * Represents individual messages, responses, and notes within a thread.
 *
 * @property int $id
 * @property int $thread_id
 * @property int $staff_id
 * @property string $type
 * @property string $body
 * @property string $format
 * @property string $created
 * @property string $updated
 * @property-read Thread     $thread
 * @property-read Staff|null $staff
 */
class ThreadEntry extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'thread_entry';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Get the thread that this entry belongs to.
     *
     * @return BelongsTo<Thread, $this>
     */
    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'id');
    }

    /**
     * Get the staff member who created this entry.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
