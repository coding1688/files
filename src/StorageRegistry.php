<?php

namespace Coding1688\Files;

use Aws\CommandInterface;
use Aws\Middleware;
use Coding1688\Files\Models\FileConfiguration;
use Coding1688\Files\Models\FileStorageAccount;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StorageRegistry
{
    /** @var array<string, FileStorageAccount|null> */
    private array $accounts = [];

    public function forgetDisk(string $disk): void
    {
        unset($this->accounts[$disk]);
    }

    /** @return array<string, mixed> */
    public function uploadSettings(): array
    {
        if (! Schema::hasTable('file_configurations')) {
            return [];
        }

        return FileConfiguration::find(1)?->only(['default_disk', 'max_upload_mb', 'allowed_extensions']) ?? [];
    }

    /** @return array<string, array<string, mixed>> */
    public function providers(): array
    {
        $defaults = (array) config('files.uploads', []);

        if ($defaults === []) {
            $moduleConfig = require __DIR__.'/../config/files.php';
            $defaults = (array) ($moduleConfig['uploads'] ?? []);
        }
        $accounts = Schema::hasTable('file_storages') ? $this->accountModel()::all()->keyBy('disk') : collect();
        $providers = [];
        foreach (array_unique([...array_keys($defaults['providers']), ...$accounts->keys()->all()]) as $disk) {
            $account = $accounts->get($disk);
            $provider = $account?->provider ?? $defaults['providers'][$disk]['provider'];
            $configuration = $account ? $this->configuration($account) : (array) config('filesystems.disks.'.$disk, []);
            $visibility = $account?->visibility ?? ($configuration['visibility'] ?? $defaults['providers'][$disk]['visibility']);
            $configured = ($configuration['driver'] ?? null) === 'local'
                ? ! empty($configuration['root'])
                : ! empty($configuration['bucket']) && ! empty($configuration['key']) && ! empty($configuration['secret'])
                    && ($provider !== 'cloudflare' || ! empty($configuration['endpoint']));
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
            $this->accounts[$disk] = Schema::hasTable('file_storages') ? $this->accountModel()::where('disk', $disk)->first() : null;
        }
        $account = $this->accounts[$disk];
        if (! $account || (in_array($disk, ['public', 'local'], true) && empty($account->configuration['url']))) {
            return Storage::disk($disk);
        }
        if (in_array($disk, ['public', 'local'], true)) {
            return Storage::build(array_replace((array) config('filesystems.disks.'.$disk), Storage::disk($disk)->getConfig(), ['url' => $account->configuration['url']]));
        }
        $filesystem = Storage::build($this->configuration($account));
        if ($account->provider === 'cloudflare' && $filesystem instanceof AwsS3V3Adapter) {
            $filesystem->getClient()->getHandlerList()->appendInit(Middleware::mapCommand(function (CommandInterface $command): CommandInterface {
                unset($command['ACL']);

                return $command;
            }), 'r2_without_acl');
        }

        return $filesystem;
    }

    private function accountModel(): string
    {
        $class = (string) config('files.models.storage_account', '');

        return $class !== '' && class_exists($class) ? $class : FileStorageAccount::class;
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
