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
use Illuminate\Support\Facades\DB;

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
            'notify_user' => 'sometimes|boolean',
            'expected_updated' => 'required|string',
        ]);

        $notifyUser = (bool) ($data['notify_user'] ?? false);

        if ($notifyUser) {
            if ((string) config('mail.event_class_owner.close_notify') !== 'laravel') {
                abort(403, 'Close-notify mail is owned by legacy.');
            }

            if (trim((string) ($data['comments'] ?? '')) === '') {
                return response()->json([
                    'message' => 'Comments required when notifying user.',
                    'errors' => ['comments' => ['A note is required when notifying the customer.']],
                ], 422);
            }

            $targetState = (string) DB::connection('legacy')->table('ticket_status')
                ->where('id', $data['status_id'])
                ->value('state');

            if ($targetState !== 'closed') {
                return response()->json([
                    'message' => 'notify_user only valid when transitioning to a closed status.',
                    'errors' => ['notify_user' => ['Only allowed when closing.']],
                ], 422);
            }
        }

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
                notifyUser: $notifyUser,
            );

            $this->logger->record(
                staff: $staff,
                action: 'status.changed',
                outcome: 'success',
                httpStatus: 302,
                ticketId: $ticket->ticket_id,
                beforeState: ['notify_user' => $notifyUser, 'mail_queued' => $notifyUser],
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
