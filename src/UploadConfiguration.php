<?php

namespace Coding1688\Files;

use Illuminate\Validation\ValidationException;

class UploadConfiguration
{
    public function __construct(private readonly StorageRegistry $storages) {}

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        $uploads = config('files.uploads');

        if (! is_array($uploads) || $uploads === []) {
            $defaults = require __DIR__.'/../config/files.php';
            $uploads = (array) ($defaults['uploads'] ?? []);
        }

        return $uploads;
    }

    /** @return array<string, array{provider: string, configured: bool, visibilities: list<string>}> */
    public function availableProviders(): array
    {
        return $this->storages->providers();
    }

    /** @return array<string, array{provider: string, configured: bool, selected: bool, enabled: bool, visibilities: list<string>}> */
    public function providers(): array
    {
        return $this->storages->providers();
    }

    /** @return list<string> */
    public function enabledDisks(): array
    {
        return collect($this->providers())
            ->filter(fn (array $provider): bool => $provider['enabled'])
            ->keys()
            ->values()
            ->all();
    }

    public function defaultDisk(): ?string
    {
        $enabled = $this->enabledDisks();
        $configured = $this->defaults()['default_disk'];
        $preferred = (string) ($this->storages->uploadSettings()['default_disk'] ?? $configured);

        return in_array($preferred, $enabled, true)
            ? $preferred
            : (in_array($configured, $enabled, true) ? $configured : ($enabled[0] ?? null));
    }

    public function publicDisk(): string
    {
        $providers = $this->providers();
        $preferred = $this->defaultDisk();
        if (($providers[$preferred]['enabled'] ?? false) && in_array('public', $providers[$preferred]['visibilities'], true)) {
            return $preferred;
        }

        foreach ($providers as $disk => $provider) {
            if ($provider['enabled'] && in_array('public', $provider['visibilities'], true)) {
                return $disk;
            }
        }

        throw ValidationException::withMessages(['disk' => __('common/file/const.no_public_storage')]);
    }

    /** @return list<string> */
    public function allowedExtensions(): array
    {
        $defaults = $this->defaults();
        $selected = $this->normalizeExtensions((array) ($this->storages->uploadSettings()['allowed_extensions'] ?? $defaults['allowed_extensions']));

        return array_values(array_diff($selected, $defaults['limits']['forbidden_extensions']));
    }

    public function maxSizeKb(): int
    {
        $defaults = $this->defaults();
        $configuredMb = $this->storages->uploadSettings()['max_upload_mb'] ?? $defaults['max_upload_mb'];
        $configuredKb = is_numeric($configuredMb) ? (int) round((float) $configuredMb * 1024) : 0;

        return max(0, min((int) $defaults['limits']['hard_max_upload_mb'] * 1024, $configuredKb));
    }

    /** @param list<string>|null $extensionSubset @return list<string> */
    public function fileRules(?array $extensionSubset = null): array
    {
        $extensions = $this->allowedExtensions();
        if ($extensionSubset !== null) {
            $extensions = array_values(array_intersect($extensions, $this->normalizeExtensions($extensionSubset)));
        }

        if ($extensions === [] || $this->maxSizeKb() === 0) {
            return ['prohibited'];
        }

        return ['required', 'file', 'max:'.$this->maxSizeKb(), 'mimes:'.implode(',', $extensions)];
    }

    /** @return list<string> */
    private function normalizeExtensions(array $extensions): array
    {
        $normalized = [];
        foreach ($extensions as $extension) {
            $extension = strtolower(ltrim(trim((string) $extension), '.'));
            if ($extension !== '' && preg_match('/^[a-z0-9]{1,16}$/', $extension)) {
                $normalized[$extension] = true;
            }
        }

        return array_keys($normalized);
    }
}
