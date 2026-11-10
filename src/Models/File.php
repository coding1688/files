<?php

namespace Coding1688\Files\Models;

use Coding1688\Files\Constants\FileType;
use Coding1688\Files\FileStorage;
use Coding1688\Files\StorageRegistry;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class File extends Model
{
    protected $fillable = ['disk', 'type', 'path', 'original_name', 'extension', 'mime_type', 'size', 'visibility', 'checksum', 'width', 'height', 'metadata', 'created_by'];

    public function getTable(): string
    {
        return (string) config('files.tables.files', 'files');
    }

    protected function casts(): array
    {
        $typeEnum = (string) config('files.type_enum', '');
        $typeEnum = $typeEnum !== '' && enum_exists($typeEnum) ? $typeEnum : FileType::class;

        return ['type' => $typeEnum, 'size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $file): void {
            if ($file->references()->exists()) {
                throw ValidationException::withMessages(['file' => __('common/file/const.in_use')]);
            }
        });
    }

    public function references(): HasMany
    {
        return $this->hasMany(FileReference::class, 'file_id');
    }

    public function replicas(): HasMany
    {
        return $this->hasMany(FileReplica::class, 'file_id');
    }

    public function publicUrl(): ?string
    {
        [$disk, $path] = $this->resolvedLocation();

        return app(FileStorage::class)->url($disk, $path, $this->visibility);
    }

    public function temporaryUrl(int|DateTimeInterface|\DateInterval $expires): ?string
    {
        [$disk, $path] = $this->resolvedLocation();

        return app(FileStorage::class)->temporaryUrl($disk, $path, $expires);
    }

    public function replicaOn(string $disk): ?FileReplica
    {
        return $this->relationLoaded('replicas')
            ? $this->replicas->firstWhere('disk', $disk)
            : $this->replicas()->where('disk', $disk)->first();
    }

    /** @return array{0: string, 1: string} */
    private function resolvedLocation(): array
    {
        foreach (app(StorageRegistry::class)->preferredDisks() as $disk) {
            if ($disk === $this->disk) {
                continue;
            }
            $replica = $this->replicaOn($disk);
            if ($replica !== null) {
                return [$disk, $replica->path];
            }
        }

        return [$this->disk, $this->path];
    }
}
