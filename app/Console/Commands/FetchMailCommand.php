<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class FetchMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:fetch-mail {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch emails from configured mailboxes and create tickets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE: No emails will be fetched');
        }

        $this->info('Fetching emails from configured mailboxes...');
        $this->line('Full implementation in Task 3');

        if ($dryRun) {
            $this->comment('No emails were processed (dry run mode)');
        }

        return self::SUCCESS;
    }
}
