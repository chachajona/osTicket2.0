<?php

namespace App\Models;

/**
 * ThreadCollaborator model for the legacy osTicket ost_thread_collaborator table.
 *
 * @property int $id
 * @property int $flags
 * @property int $thread_id
 * @property int $user_id
 * @property string $role
 * @property string $created
 * @property string $updated
 * @property-read Thread    $thread
 * @property-read LegacyUser|null $user
 */
class ThreadCollaborator extends LegacyModel
{
    protected $table = 'thread_collaborator';

    protected $primaryKey = 'id';

    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(LegacyUser::class, 'user_id', 'id');
    }
}
