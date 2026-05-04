<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AssignmentService
{
    public function __construct(
        private readonly ThreadEventWriter $threadEvents,
        private readonly NotePostingService $notePostingService,
        private readonly TicketCacheUpdater $ticketCacheUpdater,
    ) {}

    public function assign(
        Ticket $ticket,
        Thread $thread,
        Staff $caller,
        string $type,
        int $assigneeId,
        ?string $comments,
        string $expectedUpdated,
    ): void {
        if (! in_array($type, ['staff', 'team'], true)) {
            throw new InvalidArgumentException("Unsupported assignment type [{$type}].");
        }

        DB::connection('legacy')->transaction(function () use ($ticket, $thread, $caller, $type, $assigneeId, $comments, $expectedUpdated): void {
            $current = $this->lockCurrentTicket($ticket);
            $before = $this->snapshot($current);

            $this->assertExpectedUpdated($current, $expectedUpdated);

            if ($type === 'staff') {
                $current->forceFill([
                    'staff_id' => $assigneeId,
                    'team_id' => 0,
                ])->save();
            } else {
                $current->forceFill([
                    'staff_id' => 0,
                    'team_id' => $assigneeId,
                ])->save();
            }

            if ($comments !== null && $comments !== '') {
                $this->notePostingService->post($current, $thread, $caller, $comments, 'text', $expectedUpdated);
            }

            $this->threadEvents->record(
                thread: $thread,
                eventName: 'assigned',
                entryId: null,
                staff: $caller,
                data: [
                    'staff' => $type === 'staff' ? ['id' => $assigneeId] : null,
                    'team' => $type === 'team' ? ['id' => $assigneeId] : null,
                    'before' => $before,
                ],
            );

            $this->ticketCacheUpdater->touch($current, $thread);
        });
    }

    public function release(
        Ticket $ticket,
        Thread $thread,
        Staff $caller,
        ?string $comments,
        string $expectedUpdated,
    ): void {
        DB::connection('legacy')->transaction(function () use ($ticket, $thread, $caller, $comments, $expectedUpdated): void {
            $current = $this->lockCurrentTicket($ticket);
            $before = $this->snapshot($current);

            $this->assertExpectedUpdated($current, $expectedUpdated);

            $current->forceFill([
                'staff_id' => 0,
                'team_id' => 0,
            ])->save();

            if ($comments !== null && $comments !== '') {
                $this->notePostingService->post($current, $thread, $caller, $comments, 'text', $expectedUpdated);
            }

            $this->threadEvents->record(
                thread: $thread,
                eventName: 'released',
                entryId: null,
                staff: $caller,
                data: [
                    'staff' => null,
                    'team' => null,
                    'before' => $before,
                ],
            );

            $this->ticketCacheUpdater->touch($current, $thread);
        });
    }

    private function assertExpectedUpdated(Ticket $ticket, string $expectedUpdated): void
    {
        if ((string) $ticket->updated !== $expectedUpdated) {
            throw new TicketModifiedConcurrentlyException($ticket->ticket_id, (string) $ticket->updated);
        }
    }

    /**
     * @return array{staff_id:int,team_id:int}
     */
    private function snapshot(Ticket $ticket): array
    {
        return [
            'staff_id' => (int) $ticket->staff_id,
            'team_id' => (int) ($ticket->team_id ?? 0),
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

        return $query->firstOrFail();
    }
}
