<?php

namespace Coding1688\Files\Console\Commands;

use Coding1688\Files\FileStorage;
use Coding1688\Files\StorageRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SmokeCommand extends Command
{
    protected $signature = 'files:smoke {disk : Storage disk to verify}';

    protected $description = 'Upload, serve, sign and delete a tiny object to verify a storage disk';

    public function handle(StorageRegistry $registry, FileStorage $storage): int
    {
        $disk = (string) $this->argument('disk');
        $providers = $registry->providers();

        if (! isset($providers[$disk])) {
            $this->error("Unknown disk: {$disk}");

            return self::FAILURE;
        }
        if (! ($providers[$disk]['enabled'] ?? false)) {
            $this->error("Disk is not enabled or not fully configured: {$disk}");

            return self::FAILURE;
        }

        $path = 'smoke/'.Str::ulid().'.txt';
        $results = [];

        try {
            $adapter = $registry->disk($disk);
            $results['write'] = $adapter->put($path, 'files-smoke') ? 'ok' : 'failed';
            $results['exists'] = $adapter->exists($path) ? 'ok' : 'failed';
            $results['url'] = (string) $adapter->url($path);
            $temporary = $storage->temporaryUrl($disk, $path, now()->addMinutes(5));
            $results['temporary_url'] = $temporary === null ? 'unsupported' : 'ok';
            $adapter->delete($path);
            $results['delete'] = $adapter->exists($path) ? 'failed' : 'ok';
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['check', 'result'], collect($results)->map(fn (string $value, string $key): array => [$key, $value])->values()->all());

        return in_array('failed', $results, true) ? self::FAILURE : self::SUCCESS;
    }
}
