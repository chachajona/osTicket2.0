<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use App\Models\ThreadEntry;
use Illuminate\Support\Facades\DB;

final class NotePostingService
{
    public function __construct(
        private readonly ThreadEventWriter $threadEvents,
        private readonly SearchIndexer $searchIndexer,
        private readonly TicketCacheUpdater $ticketCacheUpdater,
    ) {}

    public function post(
        Ticket $ticket,
        Thread $thread,
        Staff $staff,
        string $body,
        string $format,
        string $expectedUpdated,
    ): ThreadEntry {
        return DB::connection('legacy')->transaction(function () use ($ticket, $thread, $staff, $body, $format, $expectedUpdated): ThreadEntry {
            $current = $this->lockCurrentTicket($ticket);

            if ((string) $current->updated !== $expectedUpdated) {
                throw new TicketModifiedConcurrentlyException($current->ticket_id, (string) $current->updated);
            }

            $entry = ThreadEntry::on('legacy')->create([
                'thread_id' => $thread->id,
                'staff_id' => $staff->staff_id,
                'type' => 'N',
                'format' => $format,
                'body' => $body,
                'title' => '',
                'poster' => $staff->displayName(),
                'created' => now(),
                'updated' => now(),
            ]);

            $this->threadEvents->record($thread, 'created', $entry->id, $staff, ['entry_id' => $entry->id]);
            $this->searchIndexer->index('THE', $entry->id, '', $entry->body ?? '');
            $this->ticketCacheUpdater->touch($current, $thread);

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
}
