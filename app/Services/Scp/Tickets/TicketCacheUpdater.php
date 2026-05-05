<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Models\Ticket;
use App\Models\Thread;
use Carbon\Carbon;

final class TicketCacheUpdater
{
    public function touch(Ticket $ticket, ?Thread $thread = null): void
    {
        $now = Carbon::now();

        $ticket->forceFill([
            'lastupdate' => $now,
            'updated' => $now,
        ])->save();

        if ($thread !== null) {
            $thread->forceFill([
                'lastresponse' => $now,
            ])->save();
        }
    }
}
