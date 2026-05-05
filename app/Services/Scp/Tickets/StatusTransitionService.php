<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Exceptions\ForbiddenStatusTransition;
use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\DB;

final class StatusTransitionService
{
    /**
     * @var list<string>
     */
    private const ALLOWED_STATES = ['open', 'onhold'];

    public function __construct(
        private readonly ThreadEventWriter $threadEvents,
        private readonly NotePostingService $notes,
        private readonly TicketCacheUpdater $ticketCacheUpdater,
    ) {}

    public function transition(
        Ticket $ticket,
        Thread $thread,
        Staff $caller,
        int $targetStatusId,
        ?string $comments,
        string $expectedUpdated,
    ): void {
        DB::connection('legacy')->transaction(function () use ($ticket, $thread, $caller, $targetStatusId, $comments, $expectedUpdated): void {
            $currentTicket = $this->lockCurrentTicket($ticket);

            if ((string) $currentTicket->updated !== $expectedUpdated) {
                throw new TicketModifiedConcurrentlyException($currentTicket->ticket_id, (string) $currentTicket->updated);
            }

            /** @var TicketStatus $from */
            $from = TicketStatus::on('legacy')->findOrFail($currentTicket->status_id);
            /** @var TicketStatus $to */
            $to = TicketStatus::on('legacy')->findOrFail($targetStatusId);

            if (! $this->transitionAllowed((string) $from->state, (string) $to->state)) {
                throw new ForbiddenStatusTransition((string) $from->state, (string) $to->state);
            }

            $currentTicket->forceFill([
                'status_id' => $to->getKey(),
            ])->save();

            if ($comments !== null && trim($comments) !== '') {
                $this->notes->post(
                    ticket: $currentTicket,
                    thread: $thread,
                    staff: $caller,
                    body: $comments,
                    format: 'text',
                    expectedUpdated: $expectedUpdated,
                );
            }

            $this->threadEvents->record(
                thread: $thread,
                eventName: 'status',
                entryId: null,
                staff: $caller,
                data: [
                    'from' => $this->statusData($from),
                    'to' => $this->statusData($to),
                ],
            );

            $this->ticketCacheUpdater->touch($currentTicket, $thread);
        });
    }

    private function transitionAllowed(string $fromState, string $toState): bool
    {
        return in_array($fromState, self::ALLOWED_STATES, true)
            && in_array($toState, self::ALLOWED_STATES, true);
    }

    /**
     * @return array{id:int,name:string,state:string}
     */
    private function statusData(TicketStatus $status): array
    {
        return [
            'id' => (int) $status->getKey(),
            'name' => (string) $status->name,
            'state' => (string) $status->state,
        ];
    }

    private function lockCurrentTicket(Ticket $ticket): Ticket
    {
        $query = Ticket::on('legacy')
            ->withoutGlobalScopes()
            ->whereKey($ticket->ticket_id);

        if (DB::connection('legacy')->getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        return $query->findOrFail($ticket->ticket_id);
    }
}
