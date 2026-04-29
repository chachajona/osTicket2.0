<?php

namespace App\Services\FileStorage;

use App\Models\File;
use App\Models\FileChunk;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyChunkReader implements FileStorageReader
{
    public function exists(File $file): bool
    {
        return FileChunk::query()->where('file_id', $file->id)->exists();
    }

    public function metadata(File $file): FileMetadata
    {
        return new FileMetadata(
            name: $file->name ?: sprintf('file-%d', $file->id),
            mime: $file->mime ?: 'application/octet-stream',
            size: (int) $file->size,
        );
    }

    public function stream(File $file): StreamedResponse
    {
        $metadata = $this->metadata($file);

        return response()->streamDownload(function () use ($file): void {
            FileChunk::query()
                ->where('file_id', $file->id)
                ->orderBy('chunk_id')
                ->each(function (FileChunk $chunk): void {
                    echo $chunk->filedata;
                    flush();
                });
        }, $metadata->name, [
            'Content-Type' => $metadata->mime ?? 'application/octet-stream',
            'Content-Length' => (string) $metadata->size,
        ]);
    }
}
