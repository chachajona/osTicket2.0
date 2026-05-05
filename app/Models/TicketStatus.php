<?php

declare(strict_types=1);

namespace App\Models;

final class TicketStatus extends LegacyModel
{
    protected $table = 'ticket_status';

    protected $primaryKey = 'id';

    public $timestamps = false;
}
