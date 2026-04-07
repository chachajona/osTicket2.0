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

        $query = Draft::query()->where('created', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No draft entries older than {$days} days found.");

            return self::SUCCESS;
        }

        $this->line("Found {$count} draft(s) older than {$days} days to delete:");
        foreach ((clone $query)->orderBy('created')->limit(5)->get() as $draft) {
            $this->line("  - Draft #{$draft->id} (created: {$draft->created})");
        }
        if ($count > 5) {
            $this->line('  ... and '.($count - 5).' more');
        }

        if (! $dryRun) {
            $query->delete();

            $this->comment("{$count} draft(s) deleted");
        } else {
            $this->comment("Would delete {$count} draft(s)");
        }

        return self::SUCCESS;
    }
}
