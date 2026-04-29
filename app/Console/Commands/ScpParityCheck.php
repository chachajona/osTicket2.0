<?php

namespace App\Console\Commands;

use App\Models\Queue;
use App\Models\Ticket;
use App\Services\Scp\LegacyQueueCriteriaParser;
use App\Services\Scp\TicketReadService;
use Illuminate\Console\Command;

class ScpParityCheck extends Command
{
    protected $signature = 'scp:parity-check {--queue=} {--sample=50}';

    protected $description = 'Sample legacy tickets through the SCP read services and report projection errors.';

    public function handle(LegacyQueueCriteriaParser $criteriaParser, TicketReadService $tickets): int
    {
        $sample = max(1, (int) $this->option('sample'));
        $query = Ticket::query()->orderByDesc('ticket_id')->limit($sample);
        $queueId = $this->option('queue');

        if ($queueId) {
            $queue = Queue::query()->find((int) $queueId);

            if (! $queue) {
                $this->error("Queue [{$queueId}] was not found.");

                return self::FAILURE;
            }

            $unsupported = $criteriaParser->apply($query, $queue->config);

            if ($unsupported !== []) {
                $this->warn('Unsupported queue criteria: '.implode('; ', $unsupported));
            }
        }

        $checked = 0;
        $failures = 0;

        foreach ($query->get() as $ticket) {
            $checked++;

            try {
                $projection = $tickets->read($ticket);

                if (($projection['ticket']['id'] ?? null) !== (int) $ticket->ticket_id) {
                    $failures++;
                    $this->line("Ticket {$ticket->ticket_id}: projection ID mismatch.");
                }
            } catch (\Throwable $exception) {
                $failures++;
                $this->line("Ticket {$ticket->ticket_id}: {$exception->getMessage()}");
            }
        }

        $this->info("Checked {$checked} ticket(s); {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
