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
                throw new RuntimeException('Failed to open legacy filesystem file for reading.');
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
        if ((string) $file->bk === 'F') {
            return $this->pluginFilesystemPath($file);
        }

        $attrs = $file->attrs ?? null;

        if (! is_string($attrs) || trim($attrs) === '') {
            return null;
        }

        $decoded = json_decode($attrs, true);

        if (is_array($decoded)) {
            foreach (['path', 'file', 'filename', 'key'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return $this->sandboxedPath($decoded[$key]);
                }
            }
        }

        return $this->sandboxedPath($attrs);
    }

    private function pluginFilesystemPath(File $file): ?string
    {
        $key = $file->key ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        return $this->sandboxedPath($key[0].DIRECTORY_SEPARATOR.$key);
    }

    private function sandboxedPath(string $candidate): ?string
    {
        $root = config('services.osticket.filesystem_root');

        if (! is_string($root) || trim($root) === '') {
            return null;
        }

        $resolvedRoot = realpath($root);

        if ($resolvedRoot === false) {
            return null;
        }

        $candidate = trim($candidate);
        $path = str_starts_with($candidate, DIRECTORY_SEPARATOR)
            ? $candidate
            : rtrim($resolvedRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$candidate;
        $resolvedPath = realpath($path);

        if ($resolvedPath === false) {
            return null;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/');
        $normalizedPath = str_replace('\\', '/', $resolvedPath);

        if ($normalizedPath !== $normalizedRoot && ! str_starts_with($normalizedPath, $normalizedRoot.'/')) {
            return null;
        }

        return $resolvedPath;
    }
}
