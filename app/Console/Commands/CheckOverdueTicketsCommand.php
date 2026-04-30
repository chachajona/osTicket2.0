<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Eloquent\Scopes\TicketAccessibleScope;
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

        $now = now();

        $query = Ticket::query()
            ->withoutGlobalScope(TicketAccessibleScope::class)
            ->where('isoverdue', 0)
            ->whereNull('closed')
            ->whereNotNull('duedate')
            ->where('duedate', '<', $now);

        // Use count() + a small sample for the preview instead of materialising
        // the entire matched set. After a system outage the overdue backlog
        // can grow to tens of thousands of rows, and loading them all would
        // waste memory and flood the console/log capture. This matches the
        // pattern used by PurgeLogsCommand and CleanupDraftsCommand.
        $count = $query->count();

        if ($count === 0) {
            $this->info('No overdue tickets found.');

            return self::SUCCESS;
        }

        $this->line("Found {$count} overdue ticket(s):");
        foreach ((clone $query)->orderBy('duedate')->limit(5)->get() as $ticket) {
            $this->line("  - Ticket #{$ticket->ticket_id} (due: {$ticket->duedate})");
        }
        if ($count > 5) {
            $this->line('  ... and '.($count - 5).' more');
        }

        if (! $dryRun) {
            $updated = $query->update(['isoverdue' => 1]);

            $this->comment("{$updated} ticket(s) marked as overdue");
        } else {
            $this->comment("Would mark {$count} ticket(s) as overdue");
        }

        return self::SUCCESS;
    }
}
