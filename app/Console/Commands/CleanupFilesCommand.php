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

    protected $description = 'Delete orphaned temporary files older than one day that are not attached to any object';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE: No files will be deleted');
        }

        // Mirror legacy AttachmentFile::deleteOrphans() semantics:
        //  - ft='T' restricts cleanup to temporary files; logos (ft='L'), backdrops
        //    (ft='B'), permanent attachments (ft='P'), and plugin-managed file types
        //    must be preserved even when they are not referenced in ost_attachment.
        //  - created < NOW() - 1 day gives in-flight uploads a grace window so the
        //    cleanup never races an attachment insert that is still in progress.
        $query = File::query()
            ->where('ft', 'T')
            ->where('created', '<', now()->subDay())
            ->whereNotIn('id', DB::connection('legacy')->table('attachment')->select('file_id'));

        $count = $query->count();

        if ($count === 0) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        $this->line("Found {$count} orphaned file(s) to delete:");
        foreach ((clone $query)->limit(5)->get() as $file) {
            $this->line("  - File #{$file->id} ({$file->name})");
        }
        if ($count > 5) {
            $this->line('  ... and '.($count - 5).' more');
        }

        if (! $dryRun) {
            (clone $query)
                ->orderBy('id')
                ->chunkById(1000, function ($files) {
                    $fileIds = $files->pluck('id');

                    FileChunk::whereIn('file_id', $fileIds)->delete();
                    File::whereIn('id', $fileIds)->delete();
                });

            FileChunk::query()
                ->whereNotIn('file_id', File::query()->select('id'))
                ->delete();

            $this->comment("{$count} file(s) deleted");
        } else {
            $this->comment("Would delete {$count} file(s)");
        }

        return self::SUCCESS;
    }
}
