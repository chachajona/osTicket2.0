<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Exceptions\ForbiddenStatusTransition;
use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\ActionLogger;
use App\Services\Scp\Tickets\StatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class StatusController extends Controller
{
    public function __construct(
        private readonly StatusTransitionService $transitions,
        private readonly ActionLogger $logger,
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        $this->authorize('setStatus', $ticket);

        $data = $request->validate([
            'status_id' => 'required|integer|exists:legacy.ticket_status,id',
            'comments' => 'nullable|string|max:65535',
            'expected_updated' => 'required|string',
        ]);

        $staff = $request->user('staff');
        $thread = $ticket->thread()->firstOrFail();

        try {
            $this->transitions->transition(
                ticket: $ticket,
                thread: $thread,
                caller: $staff,
                targetStatusId: $data['status_id'],
                comments: $data['comments'] ?? null,
                expectedUpdated: $data['expected_updated'],
            );

            $this->logger->record(
                staff: $staff,
                action: 'status.changed',
                outcome: 'success',
                httpStatus: 302,
                ticketId: $ticket->ticket_id,
                request: $request,
            );

            return back();
        } catch (ForbiddenStatusTransition) {
            $this->logger->recordFailure(
                staff: $staff,
                action: 'status.changed',
                httpStatus: 422,
                ticketId: $ticket->ticket_id,
                errorClass: ForbiddenStatusTransition::class,
                request: $request,
            );

            return response()->json([
                'message' => 'Forbidden status transition',
            ], 422);
        } catch (TicketModifiedConcurrentlyException) {
            $this->logger->recordFailure(
                staff: $staff,
                action: 'status.changed',
                httpStatus: 409,
                ticketId: $ticket->ticket_id,
                errorClass: TicketModifiedConcurrentlyException::class,
                request: $request,
            );

            return response()->json([
                'message' => 'Ticket was modified',
            ], 409);
        }
    }
}
