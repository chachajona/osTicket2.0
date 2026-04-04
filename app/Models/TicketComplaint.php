<?php

namespace App\Models;

/**
 * TicketComplaint model for the legacy osTicket ost_ticket_complaint table.
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
class TicketComplaint extends LegacyModel
{
    protected $table = 'ticket_complaint';

    protected $primaryKey = 'id';
}
