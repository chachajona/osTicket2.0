<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Ticket;
use App\Services\Scp\Tickets\LockService;
use Closure;
use Illuminate\Http\Request;

final class EnforceTicketLock
{
    public function __construct(
        private readonly LockService $locks,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $ticket = $request->route('ticket');

        if (! $ticket instanceof Ticket) {
            $ticket = Ticket::findOrFail($ticket);
        }

        $staff = $request->user('staff');

        $this->locks->assertHeldBy($staff, $ticket);

        return $next($request);
    }
}
