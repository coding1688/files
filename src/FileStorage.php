<?php

namespace Coding1688\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class FileStorage
{
    public function __construct(private readonly StorageRegistry $storages) {}

    public function store(UploadedFile $file, string $disk, string $directory, ?string $visibility = null): StoredFile
    {
        $extension = $this->extension($file);
        $filename = (string) Str::ulid().($extension ? '.'.$extension : '');
        $path = $this->storages->disk($disk)->putFileAs(trim($directory, '/'), $file, $filename, $visibility === null ? [] : ['visibility' => $visibility]);
        if (! is_string($path)) {
            throw new RuntimeException('Unable to store uploaded file.');
        }
        [$width, $height] = $this->dimensions($file);
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            $this->storages->disk($disk)->delete($path);
            throw new RuntimeException('Unable to checksum uploaded file.');
        }

        return new StoredFile(
            path: $path,
            originalName: mb_substr(basename($file->getClientOriginalName()), 0, 256),
            extension: $extension,
            mimeType: $file->getMimeType() ?: 'application/octet-stream',
            size: $file->getSize(),
            checksum: $checksum,
            width: $width,
            height: $height,
        );
    }

    public function delete(string $disk, string $path): void
    {
        if (! $this->storages->disk($disk)->delete($path)) {
            throw new RuntimeException('Unable to delete stored file.');
        }
    }

    public function url(string $disk, string $path, string $visibility): ?string
    {
        return $visibility === 'public' ? $this->storages->disk($disk)->url($path) : null;
    }

    /** @param  array<string, mixed>  $options */
    public function temporaryUrl(string $disk, string $path, int|\DateTimeInterface|\DateInterval $expires, array $options = []): ?string
    {
        try {
            return $this->storages->disk($disk)->temporaryUrl($path, $expires, $options);
        } catch (\Throwable) {
            // Local and other non-signing drivers fall back to no URL.
            return null;
        }
    }

    private function extension(UploadedFile $file): ?string
    {
        if (strtolower($file->getClientOriginalExtension()) === 'glb' && file_get_contents($file->getRealPath(), false, null, 0, 4) === 'glTF') {
            return 'glb';
        }
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        return preg_match('/^[a-z0-9]{1,16}$/', $extension) ? $extension : null;
    }

    /** @return array{0: ?int, 1: ?int} */
    private function dimensions(UploadedFile $file): array
    {
        if (! str_starts_with($file->getMimeType() ?: '', 'image/')) {
            return [null, null];
        }
        $dimensions = @getimagesize($file->getRealPath());

        return $dimensions ? [(int) $dimensions[0], (int) $dimensions[1]] : [null, null];
    }
}
