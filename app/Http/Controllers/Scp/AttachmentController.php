<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\FileStorage\FileStorageReaderResolver;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function __construct(private readonly FileStorageReaderResolver $readers) {}

    public function download(File $file): Response
    {
        $reader = $this->readers->resolve($file);

        abort_unless($reader->exists($file), 404);

        return $reader->stream($file);
    }
}
