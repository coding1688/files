<?php

namespace Coding1688\Files;

use Coding1688\Files\Models\File;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ImageLinks
{
    /** @var array<int, File|null> */
    private array $resolvedFiles = [];

    /** @return array{cover_file_id: ?int, external_cover_url: ?string} */
    public function cover(?string $url): array
    {
        $url = trim((string) $url);
        $id = $url === '' ? null : (app(FileReferences::class)->idsFromUrls($url)[0] ?? null);

        return ['cover_file_id' => $id, 'external_cover_url' => $id !== null || $url === '' ? null : $url];
    }

    /** @return list<array{file_id: int}|array{external_url: string}> */
    public function gallery(?array $images): array
    {
        $result = [];
        foreach ($images ?? [] as $image) {
            if (is_string($image)) {
                $cover = $this->cover($image);
                $image = $cover['cover_file_id'] !== null ? ['file_id' => $cover['cover_file_id']] : ['external_url' => $cover['external_cover_url']];
            }
            if (! empty($image['file_id'])) {
                $image = ['file_id' => (int) $image['file_id']];
            } elseif (! empty($image['external_url'])) {
                $cover = $this->cover($image['external_url']);
                $image = $cover['cover_file_id'] !== null ? ['file_id' => $cover['cover_file_id']] : ['external_url' => $cover['external_cover_url']];
            } else {
                continue;
            }
            $result[json_encode($image)] = $image;
        }

        return array_values($result);
    }

    /** @param list<int> $ids */
    public function validatePublicImages(array $ids, string $field): void
    {
        if ($ids === []) {
            return;
        }
        $files = (ModelResolver::resolve('file'))::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        if ($files->count() !== count(array_unique($ids)) || $files->contains(fn (File $file): bool => $file->visibility !== 'public' || ! str_starts_with($file->mime_type, 'image/'))) {
            throw ValidationException::withMessages([$field => __('validation.exists', ['attribute' => $field])]);
        }
    }

    /** @return Collection<int, File> */
    public function files(array $images): Collection
    {
        $ids = array_values(array_unique(array_filter(array_column($images, 'file_id'))));
        if ($ids === []) {
            return collect();
        }
        $missing = array_values(array_diff($ids, array_keys($this->resolvedFiles)));
        if ($missing !== []) {
            $files = (ModelResolver::resolve('file'))::whereIn('id', $missing)->get()->keyBy('id');
            foreach ($missing as $id) {
                $this->resolvedFiles[$id] = $files->get($id);
            }
        }

        return collect($this->resolvedFiles)->only($ids)->filter();
    }

    public function forget(int $id): void
    {
        unset($this->resolvedFiles[$id]);
    }

    public function flush(): void
    {
        $this->resolvedFiles = [];
    }

    /** @return list<array<string, mixed>> */
    public function presentation(array $images): array
    {
        $files = $this->files($images);

        return array_map(fn (array $image): array => $image + ['url' => isset($image['file_id']) ? $files->get($image['file_id'])?->publicUrl() : $image['external_url']], $images);
    }
}
