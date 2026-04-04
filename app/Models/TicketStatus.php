<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TicketStatus model for the legacy osTicket ost_ticket_status table.
 *
 * @property int $id
 * @property string $name
 * @property string $state
 * @property int $mode
 * @property int $flags
 * @property int $sort
 * @property string $properties
 * @property string $created
 * @property string $updated
 * @property-read Collection<int, Ticket> $tickets
 */
class TicketStatus extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_status';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Get all tickets with this status.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'status_id', 'id');
    }
}
