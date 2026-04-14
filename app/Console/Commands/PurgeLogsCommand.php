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

        $query = Syslog::query()->where('created', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No log entries older than {$days} days found.");

            return self::SUCCESS;
        }

        $this->line("Found {$count} log entry(ies) older than {$days} days to delete:");
        foreach ((clone $query)->orderBy('created')->limit(5)->get() as $log) {
            $this->line("  - Log #{$log->log_id} (created: {$log->created})");
        }
        if ($count > 5) {
            $this->line('  ... and '.($count - 5).' more');
        }

        if (! $dryRun) {
            $deleted = $query->delete();

            $this->comment("{$deleted} log entry(ies) deleted");
        } else {
            $this->comment("Would delete {$count} log entry(ies)");
        }

        return self::SUCCESS;
    }
}
