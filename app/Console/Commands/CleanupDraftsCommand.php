<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Draft;
use Illuminate\Console\Command;

final class CleanupDraftsCommand extends Command
{
    protected $signature = 'drafts:cleanup {--dry-run} {--days=30}';

    protected $description = 'Delete old draft entries';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');

        if ($dryRun) {
            $this->info('DRY RUN MODE: No drafts will be deleted');
        }

        $cutoffDate = now()->subDays($days);

        $oldDrafts = Draft::query()
            ->where('created', '<', $cutoffDate)
            ->get();

        if ($oldDrafts->isEmpty()) {
            $this->info("No draft entries older than {$days} days found.");

            return self::SUCCESS;
        }

        $this->line("Found {$oldDrafts->count()} draft(s) older than {$days} days to delete:");
        foreach ($oldDrafts->take(5) as $draft) {
            $this->line("  - Draft #{$draft->draft_id} (created: {$draft->created})");
        }
        if ($oldDrafts->count() > 5) {
            $this->line('  ... and '.($oldDrafts->count() - 5).' more');
        }

        if (! $dryRun) {
            Draft::query()
                ->where('created', '<', $cutoffDate)
                ->delete();

            $this->comment("{$oldDrafts->count()} draft(s) deleted");
        } else {
            $this->comment("Would delete {$oldDrafts->count()} draft(s)");
        }

        return self::SUCCESS;
    }
}
