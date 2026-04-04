<?php

namespace App\Models;

/**
 * ThreadEntryEmail model for the legacy osTicket ost_thread_entry_email table.
 *
 * Stores email metadata for thread entries (message-id, headers).
 *
 * @property int $id
 * @property int $thread_entry_id
 * @property int $email_id
 * @property string $mid
 * @property string $headers
 * @property-read ThreadEntry $threadEntry
 */
class ThreadEntryEmail extends LegacyModel
{
    protected $table = 'thread_entry_email';

    protected $primaryKey = 'id';

    public function threadEntry()
    {
        return $this->belongsTo(ThreadEntry::class, 'thread_entry_id', 'id');
    }
}
