<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Syslog;
use Illuminate\Console\Command;

final class PurgeLogsCommand extends Command
{
    protected $signature = 'system:purge-logs {--dry-run} {--days=90}';

    protected $description = 'Delete log entries older than specified days';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');

        if ($dryRun) {
            $this->info('DRY RUN MODE: No logs will be deleted');
        }

        $cutoffDate = now()->subDays($days);

        $oldLogs = Syslog::query()
            ->where('created', '<', $cutoffDate)
            ->get();

        if ($oldLogs->isEmpty()) {
            $this->info("No log entries older than {$days} days found.");

            return self::SUCCESS;
        }

        $this->line("Found {$oldLogs->count()} log entry(ies) older than {$days} days to delete:");
        foreach ($oldLogs->take(5) as $log) {
            $this->line("  - Log #{$log->log_id} (created: {$log->created})");
        }
        if ($oldLogs->count() > 5) {
            $this->line('  ... and '.($oldLogs->count() - 5).' more');
        }

        if (! $dryRun) {
            Syslog::query()
                ->where('created', '<', $cutoffDate)
                ->delete();

            $this->comment("{$oldLogs->count()} log entry(ies) deleted");
        } else {
            $this->comment("Would delete {$oldLogs->count()} log entry(ies)");
        }

        return self::SUCCESS;
    }
}
