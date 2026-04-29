<?php

namespace App\Services\FileStorage;

use App\Models\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyFilesystemReader implements FileStorageReader
{
    public function exists(File $file): bool
    {
        $path = $this->path($file);

        return $path !== null && is_file($path);
    }

    public function metadata(File $file): FileMetadata
    {
        $path = $this->path($file);

        return new FileMetadata(
            name: $file->name ?: basename((string) $path),
            mime: $file->mime ?: 'application/octet-stream',
            size: $path && is_file($path) ? (int) filesize($path) : (int) $file->size,
        );
    }

    public function stream(File $file): StreamedResponse
    {
        $path = $this->path($file);

        if ($path === null || ! is_file($path)) {
            throw new RuntimeException('Legacy filesystem file is missing.');
        }

        $metadata = $this->metadata($file);

        return response()->streamDownload(function () use ($path): void {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;
            }

            while (! feof($handle)) {
                echo fread($handle, 1024 * 512);
                flush();
            }

            fclose($handle);
        }, $metadata->name, [
            'Content-Type' => $metadata->mime ?? 'application/octet-stream',
            'Content-Length' => (string) $metadata->size,
        ]);
    }

    private function path(File $file): ?string
    {
        $attrs = $file->attrs ?? null;

        if (! is_string($attrs) || trim($attrs) === '') {
            return null;
        }

        $decoded = json_decode($attrs, true);

        if (is_array($decoded)) {
            foreach (['path', 'file', 'filename', 'key'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        return $attrs;
    }
}
