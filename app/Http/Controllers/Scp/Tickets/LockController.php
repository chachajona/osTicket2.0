<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Exceptions\TicketLockedException;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\ActionLogger;
use App\Services\Scp\Tickets\LockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LockController extends Controller
{
    public function __construct(
        private readonly LockService $locks,
        private readonly ActionLogger $audit,
    ) {}

    public function acquire(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');

        try {
            $lock = $this->locks->acquire($staff, $ticket);

            $this->audit->record(
                staff: $staff,
                action: 'lock.acquired',
                outcome: 'success',
                httpStatus: 200,
                ticketId: $ticket->ticket_id,
                lockId: (string) $lock->lock_id,
                request: $request,
            );

            return response()->json([
                'lock_id' => (string) $lock->lock_id,
                'held_by_staff_id' => (int) $lock->staff_id,
                'expires_at' => $lock->expire,
            ]);
        } catch (TicketLockedException $e) {
            $this->audit->recordFailure(
                staff: $staff,
                action: 'lock.acquired',
                httpStatus: 423,
                ticketId: $ticket->ticket_id,
                errorClass: TicketLockedException::class,
                request: $request,
            );

            throw $e;
        }
    }

    public function renew(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');

        try {
            $lock = $this->locks->renew($staff, $ticket);

            $this->audit->record(
                staff: $staff,
                action: 'lock.renewed',
                outcome: 'success',
                httpStatus: 200,
                ticketId: $ticket->ticket_id,
                lockId: (string) $lock->lock_id,
                request: $request,
            );

            return response()->json([
                'lock_id' => (string) $lock->lock_id,
                'held_by_staff_id' => (int) $lock->staff_id,
                'expires_at' => $lock->expire,
            ]);
        } catch (TicketLockedException $e) {
            $this->audit->recordFailure(
                staff: $staff,
                action: 'lock.renewed',
                httpStatus: 423,
                ticketId: $ticket->ticket_id,
                errorClass: TicketLockedException::class,
                request: $request,
            );

            throw $e;
        }
    }

    public function release(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');

        $this->locks->release($staff, $ticket);

        $this->audit->record(
            staff: $staff,
            action: 'lock.released',
            outcome: 'success',
            httpStatus: 204,
            ticketId: $ticket->ticket_id,
            request: $request,
        );

        return response()->json(status: 204);
    }
}
