<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Exceptions\ForbiddenStatusTransition;
use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Mail\CloseNotifyMail;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\Scp\Mail\EmailInfoPersister;
use App\Services\Scp\Mail\MessageIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class StatusTransitionService
{
    /**
     * @var list<string>
     */
    private const MUTABLE_STATES = ['open', 'onhold'];

    /**
     * @var list<string>
     */
    private const TARGET_STATES = ['open', 'onhold', 'closed'];

    public function __construct(
        private readonly ThreadEventWriter $threadEvents,
        private readonly NotePostingService $notes,
        private readonly TicketCacheUpdater $ticketCacheUpdater,
        private readonly MessageIdGenerator $messageIds,
        private readonly EmailInfoPersister $emailInfo,
    ) {}

    public function transition(
        Ticket $ticket,
        Thread $thread,
        Staff $caller,
        int $targetStatusId,
        ?string $comments,
        string $expectedUpdated,
        bool $notifyUser = false,
    ): void {
        DB::connection('legacy')->transaction(function () use ($ticket, $thread, $caller, $targetStatusId, $comments, $expectedUpdated, $notifyUser): void {
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

            $noteEntry = null;

            if ($comments !== null && trim($comments) !== '') {
                $noteEntry = $this->notes->post(
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

            if ($notifyUser && $noteEntry instanceof ThreadEntry && (string) $to->state === 'closed') {
                $this->queueCloseNotifyMail($currentTicket, $thread, $noteEntry, $caller, (string) $comments);
            }
        });
    }

    private function transitionAllowed(string $fromState, string $toState): bool
    {
        return in_array($fromState, self::MUTABLE_STATES, true)
            && in_array($toState, self::TARGET_STATES, true);
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

    private function queueCloseNotifyMail(
        Ticket $ticket,
        Thread $thread,
        ThreadEntry $entry,
        Staff $caller,
        string $comments,
    ): void {
        $messageId = $this->messageIds->next($ticket, $entry);
        $inReplyTo = $this->messageIds->inReplyTo($thread);
        $references = $this->messageIds->references($thread);

        $this->emailInfo->record(
            entry: $entry,
            messageId: $messageId,
            headers: $this->headersBlock($messageId, $inReplyTo, $references),
            emailId: $ticket->department?->email?->email_id !== null ? (int) $ticket->department->email->email_id : null,
        );

        Mail::to((string) ($ticket->user?->defaultEmail?->address ?? ''))->queue(new CloseNotifyMail(
            ticket: $ticket,
            entry: $entry,
            staff: $caller,
            comments: $comments,
            messageId: $messageId,
            inReplyTo: $inReplyTo,
            references: $references,
        ));
    }

    private function headersBlock(string $messageId, ?string $inReplyTo, string $references): string
    {
        $headers = ['Message-ID: '.$messageId];

        if ($inReplyTo !== null && $inReplyTo !== '') {
            $headers[] = 'In-Reply-To: '.$inReplyTo;
        }

        if ($references !== '') {
            $headers[] = 'References: '.$references;
        }

        return implode("\r\n", $headers);
    }
}
