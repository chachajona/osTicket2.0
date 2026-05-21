<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Mail\StaffReplyMail;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Services\Scp\Mail\EmailInfoPersister;
use App\Services\Scp\Mail\MessageIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class ReplyPostingService
{
    public function __construct(
        private readonly ThreadEventWriter $threadEvents,
        private readonly SearchIndexer $searchIndexer,
        private readonly TicketCacheUpdater $ticketCacheUpdater,
        private readonly StatusTransitionService $statusTransitions,
        private readonly MessageIdGenerator $messageIds,
        private readonly EmailInfoPersister $emailInfo,
    ) {}

    public function post(
        Ticket $ticket,
        Thread $thread,
        Staff $staff,
        string $body,
        string $format,
        string $signatureChoice,
        ?int $replyStatusId,
        string $expectedUpdated,
    ): ThreadEntry {
        return DB::connection('legacy')->transaction(function () use ($ticket, $thread, $staff, $body, $format, $signatureChoice, $replyStatusId, $expectedUpdated): ThreadEntry {
            $current = $this->lockCurrentTicket($ticket);

            if ((string) $current->updated !== $expectedUpdated) {
                throw new TicketModifiedConcurrentlyException($current->ticket_id, (string) $current->updated);
            }

            $entry = ThreadEntry::on('legacy')->create([
                'thread_id' => $thread->id,
                'staff_id' => $staff->staff_id,
                'type' => 'R',
                'format' => $format,
                'body' => $body,
                'title' => '',
                'poster' => $staff->displayName(),
                'created' => now(),
                'updated' => now(),
            ]);

            $messageId = $this->messageIds->next($current, $entry);
            $inReplyTo = $this->messageIds->inReplyTo($thread);
            $references = $this->messageIds->references($thread);

            $this->emailInfo->record(
                entry: $entry,
                messageId: $messageId,
                headers: $this->headersBlock($messageId, $inReplyTo, $references),
                emailId: $this->resolveDeptEmailId($current),
            );

            if ($replyStatusId !== null) {
                $this->statusTransitions->transition(
                    ticket: $current,
                    thread: $thread,
                    caller: $staff,
                    targetStatusId: $replyStatusId,
                    comments: null,
                    expectedUpdated: $expectedUpdated,
                    notifyUser: false,
                );
            }

            $this->threadEvents->record($thread, 'created', $entry->id, $staff, ['entry_id' => $entry->id]);
            $this->searchIndexer->index('THE', $entry->id, '', (string) $entry->body);
            $this->ticketCacheUpdater->touch($current, $thread);

            Mail::to($this->customerEmailFor($current))->queue(new StaffReplyMail(
                ticket: $current,
                entry: $entry,
                staff: $staff,
                signatureChoice: $signatureChoice,
                messageId: $messageId,
                inReplyTo: $inReplyTo,
                references: $references,
            ));

            return $entry;
        });
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

    private function resolveDeptEmailId(Ticket $ticket): ?int
    {
        return $ticket->department?->email?->email_id !== null
            ? (int) $ticket->department->email->email_id
            : null;
    }

    private function customerEmailFor(Ticket $ticket): string
    {
        return (string) ($ticket->user?->defaultEmail?->address ?? '');
    }
}
