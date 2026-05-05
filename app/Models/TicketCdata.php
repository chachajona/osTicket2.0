<?php

namespace App\Models;

/**
 * TicketCdata model for the legacy osTicket ost_ticket__cdata table.
 *
 * Stores custom field data associated with tickets.
 *
 * @property int $ticket_id
 * @property string $subject
 * @property string $priority
 */
class TicketCdata extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket__cdata';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ticket_id';

    public $incrementing = false;
}
