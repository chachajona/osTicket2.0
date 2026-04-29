<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\File;
use App\Models\Ticket;
use App\Services\FileStorage\FileStorageReaderResolver;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function __construct(private readonly FileStorageReaderResolver $readers) {}

    public function download(File $file): Response
    {
        abort_unless($this->canDownload($file), 404);

        $reader = $this->readers->resolve($file);

        abort_unless($reader->exists($file), 404);

        return $reader->stream($file);
    }

    private function canDownload(File $file): bool
    {
        $directTicketIds = Attachment::query()
            ->where('file_id', $file->id)
            ->where('object_type', 'T')
            ->pluck('object_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($directTicketIds !== [] && Ticket::query()->whereIn('ticket_id', $directTicketIds)->exists()) {
            return true;
        }

        $entryIds = Attachment::query()
            ->where('file_id', $file->id)
            ->whereIn('object_type', ['H', 'E', 'M', 'R', 'N'])
            ->pluck('object_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($entryIds === []) {
            return false;
        }

        return Ticket::query()
            ->whereHas('thread.entries', fn ($query) => $query->whereIn('thread_entry.id', $entryIds))
            ->exists();
    }
}
