<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thread model for the legacy osTicket ost_thread table.
 *
 * Threads are polymorphic containers: object_type='T' for tickets, 'A' for tasks.
 *
 * @property int $id
 * @property int $object_id
 * @property string $object_type
 * @property string $created
 * @property-read Collection<int, ThreadEntry> $entries
 * @property-read Ticket|null $ticket
 */
class Thread extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'thread';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Get the entries for the thread, ordered chronologically.
     *
     * @return HasMany<ThreadEntry, $this>
     */
    public function entries()
    {
        return $this->hasMany(ThreadEntry::class, 'thread_id', 'id')->orderBy('created', 'asc');
    }

    /**
     * Get the ticket that owns the thread.
     *
     * Uses object_id as the foreign key, mapping to ticket_id on the tickets table.
     * The thread's object_type column determines the polymorphic type (T=Ticket, A=Task).
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket()
    {
        return $this->belongsTo(
            Ticket::class,
            'object_id',
            'ticket_id'
        );
    }
}
