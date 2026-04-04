<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

final class CheckOverdueTicketsCommand extends Command
{
    protected $signature = 'tickets:check-overdue {--dry-run}';

    protected $description = 'Check for overdue tickets and mark them as overdue';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE: No tickets will be updated');
        }

        $overdueTickets = Ticket::query()
            ->where('isoverdue', '0')
            ->whereNull('closed')
            ->whereNotNull('duedate')
            ->where('duedate', '<', now())
            ->get();

        if ($overdueTickets->isEmpty()) {
            $this->info('No overdue tickets found.');

            return self::SUCCESS;
        }

        $this->line("Found {$overdueTickets->count()} overdue ticket(s):");
        foreach ($overdueTickets as $ticket) {
            $this->line("  - Ticket #{$ticket->ticket_id} (due: {$ticket->duedate})");
        }

        if (! $dryRun) {
            Ticket::query()
                ->where('isoverdue', '0')
                ->whereNull('closed')
                ->whereNotNull('duedate')
                ->where('duedate', '<', now())
                ->update(['isoverdue' => '1']);

            $this->comment("{$overdueTickets->count()} ticket(s) marked as overdue");
        } else {
            $this->comment("Would mark {$overdueTickets->count()} ticket(s) as overdue");
        }

        return self::SUCCESS;
    }
}
