<?php

namespace App\Services\FileStorage;

class FileMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $mime,
        public readonly int $size,
    ) {}
}
