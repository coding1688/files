<?php

namespace Coding1688\Files\Http\Resources;

use Coding1688\Files\UploadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disk' => $this->disk,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'references_count' => $this->whenCounted('references'),
            'replicas' => $this->whenLoaded('replicas', fn () => $this->replicas->map(fn ($replica): array => [
                'disk' => $replica->disk, 'path' => $replica->path, 'checksum' => $replica->checksum,
                'synced_at' => $replica->updated_at?->toIso8601String(),
            ])),
            'references' => $this->whenLoaded('references', fn () => $this->references->map(fn ($reference): array => [
                'id' => $reference->id,
                'reference_type' => class_basename($reference->reference_type),
                'reference_id' => $reference->reference_id,
                'field' => $reference->field,
            ])),
            'provider' => app(UploadConfiguration::class)->availableProviders()[$this->disk]['provider'] ?? $this->disk,
            'path' => $this->path,
            'url' => $this->publicUrl(),
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'visibility' => $this->visibility,
            'checksum' => $this->checksum,
            'width' => $this->width,
            'height' => $this->height,
            'is_image' => str_starts_with($this->mime_type, 'image/'),
            'uploader' => $this->whenLoaded('uploader', fn (): ?array => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
