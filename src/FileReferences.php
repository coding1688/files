<?php

namespace Coding1688\Files;

use Coding1688\Files\Models\File;
use Coding1688\Files\Models\FileReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FileReferences
{
    public function syncModel(Model $owner): void
    {
        DB::transaction(function () use ($owner): void {
            $current = $owner->newQuery()->lockForUpdate()->find($owner->getKey());
            if (! $current) {
                return;
            }
            $this->sync($current, $this->fieldsFor($current));
        });
    }

    public function validate(Model $owner): void
    {
        $fields = $this->fieldsFor($owner);
        $requested = collect($fields)->flatten()->map(fn ($id): int => (int) $id)->unique();
        if ($requested->isEmpty()) {
            return;
        }
        $found = File::whereIn('id', $requested->all())->pluck('id')->map(fn ($id): int => (int) $id);
        $missing = $requested->diff($found);
        if ($missing->isEmpty()) {
            return;
        }
        $field = collect($fields)->search(fn (array $ids): bool => collect($ids)->map(fn ($id): int => (int) $id)->intersect($missing)->isNotEmpty());

        throw ValidationException::withMessages([(is_string($field) && $field !== '' ? $field : 'files') => __('files::file.missing')]);
    }

    /** @return array<string, list<int>> */
    private function fieldsFor(Model $owner): array
    {
        $fields = [];
        foreach ($owner->fileReferenceFields() as $field) {
            $idField = $owner->fileReferenceIdFields()[$field] ?? null;
            $value = $owner->getAttribute($idField ?? $field);
            $fields[$field] = $idField === null ? $this->idsFromUrls($value) : array_values(array_unique(array_map('intval', array_filter((array) $value))));
        }

        return $fields;
    }

    /** @param array<string, list<int>> $fields */
    public function sync(Model $owner, array $fields): void
    {
        DB::transaction(function () use ($owner, $fields): void {
            $owner->newQuery()->lockForUpdate()->findOrFail($owner->getKey());
            $query = FileReference::where('reference_type', $owner->getMorphClass())->where('reference_id', $owner->getKey());
            $requested = collect($fields)->flatten()->map(fn ($id): int => (int) $id)->unique()->sort()->values();
            $existing = (clone $query)->pluck('file_id');
            $locked = File::whereIn('id', $requested->merge($existing)->unique()->sort()->values())->orderBy('id')->lockForUpdate()->pluck('id');
            if ($requested->diff($locked)->isNotEmpty()) {
                throw ValidationException::withMessages(['files' => __('files::file.missing')]);
            }

            $query->delete();
            foreach ($fields as $field => $ids) {
                foreach (array_unique($ids) as $id) {
                    FileReference::create([
                        'file_id' => $id,
                        'reference_type' => $owner->getMorphClass(),
                        'reference_id' => $owner->getKey(),
                        'field' => $field,
                    ]);
                }
            }
        });
    }

    /** @return list<int> */
    public function idsFromUrls(mixed $value): array
    {
        $urls = $this->urls($value);
        if ($urls === []) {
            return [];
        }
        $paths = [];
        $basePaths = [];
        foreach (app(UploadConfiguration::class)->availableProviders() as $disk => $provider) {
            if (! $provider['configured'] || ! in_array('public', $provider['visibilities'], true)) {
                continue;
            }
            $basePaths[] = rtrim(rawurldecode((string) parse_url(app(StorageRegistry::class)->disk($disk)->url(''), PHP_URL_PATH)), '/').'/';
        }
        foreach ($urls as $url) {
            $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
            foreach ($basePaths as $base) {
                if (str_starts_with($path, $base)) {
                    $paths[] = substr($path, strlen($base));
                }
            }
        }
        if ($paths === []) {
            return [];
        }

        return File::whereIn('path', array_unique($paths))->get()->filter(function (File $file) use ($urls): bool {
            $canonical = $file->publicUrl();
            if ($canonical === null) {
                return false;
            }
            $canonicalPath = rawurldecode((string) parse_url($canonical, PHP_URL_PATH));
            $canonicalHost = strtolower((string) parse_url($canonical, PHP_URL_HOST));
            foreach ($urls as $url) {
                $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if ($path === $canonicalPath && ($host === '' || $host === $canonicalHost)) {
                    return true;
                }
            }

            return false;
        })->modelKeys();
    }

    /** @return list<string> */
    private function urls(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_merge([], ...array_map(fn ($entry): array => $this->urls($entry), array_values($value)))));
        }
        if (! is_string($value) || $value === '') {
            return [];
        }
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('~(?:https?://[^\\s"\'<>]+|(?<![:/])/(?!/)[^\\s"\'<>]+)~u', $value, $matches);

        return array_values(array_unique($matches[0]));
    }
}
