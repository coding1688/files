<?php

namespace Coding1688\Files\Jobs;

use Coding1688\Files\Models\FileSyncTask;
use Coding1688\Files\StorageSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FileSyncBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $taskId) {}

    public function handle(StorageSynchronizer $synchronizer): void
    {
        $synchronizer->process($this->taskId);
    }

    public function failed(?Throwable $exception): void
    {
        $task = FileSyncTask::find($this->taskId);
        if ($task && in_array($task->status, ['pending', 'running'], true)) {
            $task->fill(['status' => 'failed', 'finished_at' => now(), 'errors' => [['file_id' => null, 'message' => 'worker_failed']]])->save();
        }
    }
}
