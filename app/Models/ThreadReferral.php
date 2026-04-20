<?php

namespace App\Models;

/**
 * ThreadReferral model for the legacy osTicket ost_thread_referral table.
 *
 * @property int $id
 * @property int $thread_id
 * @property int $object_id
 * @property string $object_type
 * @property string $created
 * @property-read Thread $thread
 */
class ThreadReferral extends LegacyModel
{
    protected $table = 'thread_referral';

    protected $primaryKey = 'id';

    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'id');
    }
}
