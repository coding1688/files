<?php

namespace Coding1688\Files\Concerns;

use Coding1688\Files\ImageLinks;
use Coding1688\Files\ModelResolver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

trait HasImageCover
{
    public static function bootHasImageCover(): void
    {
        static::saving(function ($model): void {
            if ($model->isDirty('cover_file_id') && ! $model->isDirty('external_cover_url') && $model->cover_file_id !== null) {
                $model->external_cover_url = null;
            } elseif ($model->isDirty('external_cover_url') && ! $model->isDirty('cover_file_id') && ! empty($model->external_cover_url)) {
                $model->cover_file_id = null;
            }
            if ($model->isDirty('external_cover_url') && $model->cover_file_id === null && ! empty($model->external_cover_url)) {
                $model->cover_url = $model->external_cover_url;
            }
            if ($model->isDirty(['cover_file_id', 'external_cover_url'])) {
                if ($model->cover_file_id !== null && ! empty($model->external_cover_url)) {
                    throw ValidationException::withMessages(['external_cover_url' => __('validation.prohibited', ['attribute' => 'external_cover_url'])]);
                }
                app(ImageLinks::class)->validatePublicImages($model->cover_file_id === null ? [] : [(int) $model->cover_file_id], 'cover_file_id');
            }
        });
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->cover_file_id === null) {
                    return $this->external_cover_url;
                }
                $file = $this->relationLoaded('coverFile') && $this->coverFile?->id === (int) $this->cover_file_id
                    ? $this->coverFile : $this->coverFile()->first();

                return $file?->publicUrl();
            },
            set: fn (?string $url): array => app(ImageLinks::class)->cover($url),
        );
    }

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::resolve('file'), 'cover_file_id');
    }
}
