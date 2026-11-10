<?php

namespace Coding1688\Files\Models;

use Coding1688\Files\Constants\FileType;
use Coding1688\Files\FileStorage;
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
        return ['type' => FileType::class, 'size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'metadata' => 'array'];
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
        return app(FileStorage::class)->url($this->disk, $this->path, $this->visibility);
    }
}
