<?php

namespace App\Services\FileStorage;

use App\Models\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface FileStorageReader
{
    public function exists(File $file): bool;

    public function metadata(File $file): FileMetadata;

    public function stream(File $file): StreamedResponse;
}
