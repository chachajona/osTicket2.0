<?php

namespace App\Services\Scp;

use App\Models\Ticket;
use App\Prototype\DynamicForms\JsonAccessorApproach;
use Illuminate\Database\QueryException;

class TicketCustomFields
{
    /**
     * @return array<string, mixed>
     */
    public function forTicket(Ticket $ticket): array
    {
        try {
            return JsonAccessorApproach::getFromTicket($ticket) ?? [];
        } catch (QueryException) {
            return [];
        }
    }
}
