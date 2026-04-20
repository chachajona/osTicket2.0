<?php

namespace App\Models;

/**
 * TicketService model for the legacy osTicket ost_ticket_service table.
 *
 * @property int $id
 * @property string $name
 * @property int $mode
 * @property int $flags
 * @property int $sort
 * @property string $properties
 * @property string $created
 * @property string $updated
 */
class TicketService extends LegacyModel
{
    protected $table = 'ticket_service';

    protected $primaryKey = 'id';
}
