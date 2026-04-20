<?php

namespace App\Models;

/**
 * ThreadEntryMerge model for the legacy osTicket ost_thread_entry_merge table.
 *
 * @property int $id
 * @property int $thread_entry_id
 * @property string $data
 * @property-read ThreadEntry $threadEntry
 */
class ThreadEntryMerge extends LegacyModel
{
    protected $table = 'thread_entry_merge';

    protected $primaryKey = 'id';

    public function threadEntry()
    {
        return $this->belongsTo(ThreadEntry::class, 'thread_entry_id', 'id');
    }
}
