<?php

namespace Coding1688\Files;

final readonly class StoredFile
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $path,
        public string $originalName,
        public ?string $extension,
        public string $mimeType,
        public int $size,
        public string $checksum,
        public ?int $width = null,
        public ?int $height = null,
        public ?array $metadata = null,
    ) {}

    /** @return array{path: string, original_name: string, extension: ?string, mime_type: string, size: int, checksum: string, width: ?int, height: ?int, metadata: ?array} */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'original_name' => $this->originalName,
            'extension' => $this->extension,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'width' => $this->width,
            'height' => $this->height,
            'metadata' => $this->metadata,
        ];
    }
}
