<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * TicketPriority model for the legacy osTicket ost_ticket_priority table.
 *
 * @property int $priority_id
 * @property string $priority
 * @property string $priority_desc
 * @property string $priority_color
 * @property int $priority_urgency
 * @property int $ispublic
 * @property-read Collection<int, Ticket> $tickets
 */
class TicketPriority extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_priority';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'priority_id';
}
