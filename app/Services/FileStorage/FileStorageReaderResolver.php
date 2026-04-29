<?php

namespace App\Services\FileStorage;

use App\Models\File;
use RuntimeException;

class FileStorageReaderResolver
{
    public function __construct(
        private readonly LegacyChunkReader $chunkReader,
        private readonly LegacyFilesystemReader $filesystemReader,
    ) {}

    public function resolve(File $file): FileStorageReader
    {
        return match ((string) ($file->bk ?? 'D')) {
            'D', '' => $this->chunkReader,
            '6' => $this->filesystemReader,
            default => throw new RuntimeException(sprintf('Unsupported legacy file backend [%s].', $file->bk)),
        };
    }
}
