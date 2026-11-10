<?php

namespace Coding1688\Files\Concerns;

use Coding1688\Files\FileReferences;
use Coding1688\Files\Models\FileReference;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasFileReferences
{
    /** @return list<string> */
    abstract public function fileReferenceFields(): array;

    /** @return array<string, string> Reference field => file ID attribute. */
    public function fileReferenceIdFields(): array
    {
        return [];
    }

    public static function bootHasFileReferences(): void
    {
        // Validate before the owner row is written so an invalid file id fails
        // the save instead of leaving it half applied.
        static::saving(function ($model): void {
            if (! $model->exists || $model->isDirty($model->fileReferenceFields())) {
                app(FileReferences::class)->validate($model);
            }
        });
        static::saved(function ($model): void {
            if ($model->wasRecentlyCreated || $model->wasChanged($model->fileReferenceFields())) {
                app(FileReferences::class)->syncModel($model);
            }
        });
        static::deleted(fn ($model) => $model->fileReferences()->delete());
    }

    public function fileReferences(): MorphMany
    {
        return $this->morphMany(FileReference::class, 'reference');
    }
}
