<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\File;
use App\Models\FileChunk;
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

        $query = File::query()
            ->whereNotIn('id', DB::connection('legacy')->table('attachment')->select('file_id'));

        $count = $query->count();

        if ($count === 0) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $this->line("Found {$count} orphaned file(s) to delete:");
        foreach ($query->limit(5)->get() as $file) {
            $this->line("  - File #{$file->id} ({$file->name})");
        }
        if ($count > 5) {
            $this->line('  ... and '.($count - 5).' more');
        }

        if (! $dryRun) {
            $orphanedIds = $query->pluck('id');

            FileChunk::whereIn('file_id', $orphanedIds)->delete();

            File::whereIn('id', $orphanedIds)->delete();

            $this->comment("{$count} file(s) deleted");
        } else {
            $this->comment("Would delete {$count} file(s)");
        }

        return self::SUCCESS;
    }
}
