<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CleanupFilesCommand extends Command
{
    protected $signature = 'files:cleanup {--dry-run}';

    protected $description = 'Delete orphaned files not attached to any ticket';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE: No files will be deleted');
        }

        $orphanedFiles = File::query()
            ->whereNotIn('id', DB::connection('legacy')->table('attachment')->select('file_id'))
            ->get();

        if ($orphanedFiles->isEmpty()) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $this->line("Found {$orphanedFiles->count()} orphaned file(s) to delete:");
        foreach ($orphanedFiles->take(5) as $file) {
            $this->line("  - File #{$file->id} ({$file->name})");
        }
        if ($orphanedFiles->count() > 5) {
            $this->line('  ... and '.($orphanedFiles->count() - 5).' more');
        }

        if (! $dryRun) {
            File::query()
                ->whereNotIn('id', DB::connection('legacy')->table('attachment')->select('file_id'))
                ->delete();

            $this->comment("{$orphanedFiles->count()} file(s) deleted");
        } else {
            $this->comment("Would delete {$orphanedFiles->count()} file(s)");
        }

        return self::SUCCESS;
    }
}
