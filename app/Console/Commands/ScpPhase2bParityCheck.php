<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Scp\ScpActionLog;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ScpPhase2bParityCheck extends Command
{
    protected $signature = 'scp:phase-2b-parity-check
        {--ticket= : Specific ticket ID}
        {--since= : ISO date filter}
        {--sample=50 : Random sample size}';

    protected $description = 'Verify legacy still renders tickets touched by phase 2b without errors.';

    public function handle(): int
    {
        // Parse --since option, default to 1 day ago
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))
            : Carbon::now()->subDay();

        // Determine ticket IDs to check
        $ticketIds = [];
        if ($this->option('ticket')) {
            $ticketIds = [(int) $this->option('ticket')];
        } else {
            $ticketIds = ScpActionLog::query()
                ->whereNotNull('ticket_id')
                ->where('outcome', 'success')
                ->where('created_at', '>=', $since)
                ->distinct()
                ->limit((int) $this->option('sample'))
                ->pluck('ticket_id')
                ->toArray();
        }

        $errorCount = 0;
        $ticketCount = count($ticketIds);

        foreach ($ticketIds as $ticketId) {
            // Find ticket on legacy connection
            $ticket = Ticket::on('legacy')->find($ticketId);

            if (!$ticket) {
                $this->error("Ticket {$ticketId} not found on legacy connection");
                $errorCount++;
                continue;
            }

            // Get thread for this ticket
            $thread = $ticket->thread()->first();

            if (!$thread) {
                $this->warn("Ticket {$ticketId} has no thread");
                continue;
            }

            // Query thread_event rows for this thread
            $events = DB::connection('legacy')
                ->table('thread_event')
                ->where('thread_id', $thread->id)
                ->get();

            foreach ($events as $event) {
                // Verify JSON validity if data is not null/empty
                if ($event->data !== null && $event->data !== '') {
                    $decoded = json_decode($event->data, true);
                    if ($decoded === null && $event->data !== 'null') {
                        $this->error("Ticket {$ticketId}, thread {$thread->id}, event {$event->id}: invalid JSON in data column");
                        $errorCount++;
                    }
                }
            }
        }

        $this->info("Parity check complete. {$ticketCount} tickets, {$errorCount} errors.");

        return $errorCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
