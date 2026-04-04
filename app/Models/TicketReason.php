<?php

namespace App\Models;

/**
 * TicketReason model for the legacy osTicket ost_ticket_reason table.
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
class TicketReason extends LegacyModel
{
    protected $table = 'ticket_reason';

    protected $primaryKey = 'id';
}
