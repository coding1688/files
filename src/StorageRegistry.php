<?php

namespace Coding1688\Files;

use Aws\CommandInterface;
use Aws\Middleware;
use Coding1688\Files\Models\FileStorageAccount;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class StorageRegistry
{
    /** @var array<string, FileStorageAccount|null> */
    private array $accounts = [];

    /** @var array<string, bool> */
    private array $tables = [];

    /** @var list<string>|null */
    private ?array $preferred = null;

    public function forgetDisk(string $disk): void
    {
        unset($this->accounts[$disk]);
        $this->preferred = null;
    }

    /**
     * Storage disks used to serve public URLs, in priority order. The database
     * override allows switching clouds without a deploy; config is the fallback.
     *
     * @return list<string>
     */
    public function preferredDisks(): array
    {
        if ($this->preferred !== null) {
            return $this->preferred;
        }
        $disks = $this->uploadSettings()['preferred_disks'] ?? null;

        if (! is_array($disks) || $disks === []) {
            $disks = config('files.preferred_disks');
        }

        if (! is_array($disks) || $disks === []) {
            $single = config('files.preferred_disk');
            $disks = is_string($single) && $single !== '' ? [$single] : [];
        }

        return $this->preferred = array_values(array_filter(array_map('strval', $disks), fn (string $disk): bool => $disk !== ''));
    }

    /**
     * Drop cached storage accounts. Call this after bulk writes that bypass
     * model events (query builder updates).
     */
    public function flush(): void
    {
        $this->accounts = [];
        $this->preferred = null;
    }

    private function hasTable(string $key): bool
    {
        $model = new (ModelResolver::resolve($key));
        $cacheKey = $model->getConnection()->getName().':'.$model->getTable();

        return $this->tables[$cacheKey] ??= $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
    }

    /** @return array<string, mixed> */
    public function uploadSettings(): array
    {
        if (! $this->hasTable('configuration')) {
            return [];
        }

        return (ModelResolver::resolve('configuration'))::find(1)?->only(['default_disk', 'max_upload_mb', 'allowed_extensions', 'preferred_disks']) ?? [];
    }

    /** @return array<string, array<string, mixed>> */
    public function providers(): array
    {
        $defaults = (array) config('files.uploads', []);

        if ($defaults === []) {
            $moduleConfig = require __DIR__.'/../config/files.php';
            $defaults = (array) ($moduleConfig['uploads'] ?? []);
        }
        $accounts = $this->hasTable('storage_account') ? $this->accountModel()::all()->keyBy('disk') : collect();
        $providers = [];
        foreach (array_unique([...array_keys($defaults['providers']), ...$accounts->keys()->all()]) as $disk) {
            $account = $accounts->get($disk);
            $provider = $account?->provider ?? $defaults['providers'][$disk]['provider'];
            $configuration = $account ? $this->configuration($account) : (array) config('filesystems.disks.'.$disk, []);
            $visibility = $account?->visibility ?? ($configuration['visibility'] ?? $defaults['providers'][$disk]['visibility']);
            $requiresEndpoint = in_array($provider, (array) config('files.endpoint_required_providers', ['cloudflare']), true);
            $configured = ($configuration['driver'] ?? null) === 'local'
                ? ! empty($configuration['root'])
                : ! empty($configuration['bucket']) && ! empty($configuration['key']) && ! empty($configuration['secret'])
                    && (! $requiresEndpoint || ! empty($configuration['endpoint']));
            $selected = $account?->enabled ?? in_array($disk, $defaults['enabled_disks'], true);
            $providers[$disk] = [
                'provider' => $provider, 'name' => $account?->name ?? $disk,
                'configured' => $configured, 'selected' => $selected, 'enabled' => $configured && $selected,
                'visibilities' => [$visibility],
            ];
        }

        return $providers;
    }

    public function disk(string $disk): FilesystemAdapter
    {
        if (! array_key_exists($disk, $this->accounts)) {
            $this->accounts[$disk] = $this->hasTable('storage_account') ? $this->accountModel()::where('disk', $disk)->first() : null;
        }
        $account = $this->accounts[$disk];
        if (! $account || (in_array($disk, ['public', 'local'], true) && empty($account->configuration['url']))) {
            return Storage::disk($disk);
        }
        if (in_array($disk, ['public', 'local'], true)) {
            return Storage::build(array_replace((array) config('filesystems.disks.'.$disk), Storage::disk($disk)->getConfig(), ['url' => $account->configuration['url']]));
        }
        $filesystem = Storage::build($this->configuration($account));
        if (in_array($account->provider, (array) config('files.strip_acl_providers', ['cloudflare']), true) && $filesystem instanceof AwsS3V3Adapter) {
            $filesystem->getClient()->getHandlerList()->appendInit(Middleware::mapCommand(function (CommandInterface $command): CommandInterface {
                unset($command['ACL']);

                return $command;
            }), 'r2_without_acl');
        }

        return $filesystem;
    }

    private function accountModel(): string
    {
        return ModelResolver::resolve('storage_account');
    }

    /** @return array<string, mixed> */
    public function configuration(FileStorageAccount $account): array
    {
        if (in_array($account->disk, ['public', 'local'], true)) {
            $configuration = (array) config('filesystems.disks.'.$account->disk);
            if (! empty($account->configuration['url'])) {
                $configuration['url'] = $account->configuration['url'];
            }

            return $configuration;
        }
        $configuration = $account->configuration;
        if (in_array($account->provider, ['local', 'local_private'], true)) {
            return [
                'driver' => 'local', 'root' => storage_path('app/'.$configuration['directory']),
                'url' => $configuration['url'] ?? null, 'visibility' => $account->visibility,
                'throw' => true, 'report' => false,
            ];
        }

        return [
            'driver' => 's3', 'key' => $account->credentials['key'] ?? null, 'secret' => $account->credentials['secret'] ?? null,
            'bucket' => $configuration['bucket'] ?? null, 'region' => $configuration['region'] ?? 'auto',
            'endpoint' => $configuration['endpoint'] ?? null, 'url' => $configuration['url'] ?? null,
            'use_path_style_endpoint' => (bool) ($configuration['use_path_style_endpoint'] ?? false),
            'visibility' => $account->visibility, 'throw' => true, 'report' => false,
            'http' => ['connect_timeout' => 5, 'timeout' => 30],
        ];
    }
}
