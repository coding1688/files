<?php

namespace Coding1688\Files\Console\Commands;

use Coding1688\Files\Jobs\FileSyncBatch;
use Coding1688\Files\ModelResolver;
use Coding1688\Files\StorageRegistry;
use Coding1688\Files\StorageSynchronizer;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'files:sync {source : Source disk} {target : Target disk} {--now : Copy in this process instead of queueing}';

    protected $description = 'Copy registered files from one storage disk to another (checksum verified, resumable)';

    public function handle(StorageRegistry $registry, StorageSynchronizer $synchronizer): int
    {
        $source = (string) $this->argument('source');
        $target = (string) $this->argument('target');
        $providers = $registry->providers();

        foreach ([$source, $target] as $disk) {
            if (! isset($providers[$disk])) {
                $this->error("Unknown disk: {$disk}");

                return self::FAILURE;
            }
            if (! ($providers[$disk]['enabled'] ?? false)) {
                $this->error("Disk is not enabled or not fully configured: {$disk}");

                return self::FAILURE;
            }
        }

        if ($source === $target) {
            $this->error('Source and target must differ.');

            return self::FAILURE;
        }

        $connection = config('files.sync.connection') ?: config('queue.default');
        if (! $this->option('now') && ! config('queue.connections.'.$connection)) {
            $this->error('The configured queue connection does not exist.');

            return self::FAILURE;
        }

        $files = $synchronizer->files($source);
        $maxId = (int) $files->max('id');
        if ($maxId === 0) {
            $this->info('No registered files on the source disk.');

            return self::SUCCESS;
        }

        $task = (ModelResolver::resolve('sync_task'))::create([
            'source_disk' => $source,
            'target_disk' => $target,
            'status' => 'pending',
            'last_file_id' => 0,
            'max_file_id' => $maxId,
            'total' => (int) $synchronizer->files($source)->where('id', '<=', $maxId)->count(),
        ]);

        $this->info("Task #{$task->id}: {$task->total} file(s) {$source} -> {$target}");

        if (! $this->option('now') && config('queue.connections.'.$connection.'.driver') !== 'sync') {
            FileSyncBatch::dispatch($task->id)
                ->onConnection($connection)
                ->onQueue((string) config('files.sync.queue', 'file-sync'))
                ->afterCommit();
            $this->info('Queued on the configured sync connection; run the worker or pass --now.');

            return self::SUCCESS;
        }

        $completed = false;
        $guard = (int) $task->total + 2;
        while (! $completed && $guard-- > 0) {
            $completed = $synchronizer->process($task->id, dispatch: false);
        }
        $task->refresh();

        $this->table(['copied', 'skipped', 'failed'], [[$task->copied, $task->skipped, $task->failed]]);

        foreach (array_slice($task->errors ?? [], 0, 10) as $error) {
            $this->warn(($error['file_id'] ?? '-').': '.($error['message'] ?? 'error'));
        }

        return $task->failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
