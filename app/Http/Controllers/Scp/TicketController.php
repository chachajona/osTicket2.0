<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\TicketReadService;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(private readonly TicketReadService $tickets) {}

    public function show(Ticket $ticket): Response
    {
        return Inertia::render('Scp/Tickets/Show', $this->tickets->read($ticket));
    }
}
