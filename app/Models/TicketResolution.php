<?php

namespace App\Models;

/**
 * TicketResolution model for the legacy osTicket ost_ticket_resolution table.
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
class TicketResolution extends LegacyModel
{
    protected $table = 'ticket_resolution';

    protected $primaryKey = 'id';
}
