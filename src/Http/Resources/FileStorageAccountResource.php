<?php

namespace Coding1688\Files\Http\Resources;

use Coding1688\Files\ModelResolver;
use Coding1688\Files\StorageRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileStorageAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'disk' => $this->disk, 'name' => $this->name_i18n[app()->getLocale()] ?? $this->name_i18n['en'] ?? '', 'name_i18n' => $this->name_i18n,
            'provider' => $this->provider, 'visibility' => $this->visibility, 'enabled' => $this->enabled,
            'configuration' => $this->configuration,
            'has_access_key' => ! empty($this->credentials['key']), 'has_secret_key' => ! empty($this->credentials['secret']),
            'configured' => app(StorageRegistry::class)->providers()[$this->disk]['configured'] ?? false,
            'is_default' => (ModelResolver::resolve('configuration'))::find(1)?->default_disk === $this->disk,
            'builtin' => in_array($this->disk, ['public', 'local'], true),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
