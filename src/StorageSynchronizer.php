<?php

namespace Coding1688\Files;

use Coding1688\Files\Jobs\FileSyncBatch;
use Coding1688\Files\Models\File;
use Coding1688\Files\Models\FileReplica;
use Coding1688\Files\Models\FileSyncTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class StorageSynchronizer
{
    public function __construct(private readonly StorageRegistry $storages) {}

    public function files(string $disk): Builder
    {
        return File::where(fn (Builder $query) => $query->where('disk', $disk)->orWhereHas('replicas', fn (Builder $replicas) => $replicas->where('disk', $disk)));
    }

    public function process(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $task = FileSyncTask::lockForUpdate()->findOrFail($id);
            if (! in_array($task->status, ['pending', 'running'], true)) {
                return;
            }
            $task->fill(['status' => 'running', 'started_at' => $task->started_at ?? now()]);
            $file = $this->files($task->source_disk)->where('id', '>', $task->last_file_id)->where('id', '<=', $task->max_file_id)->orderBy('id')->lockForUpdate()->first();
            if (! $file) {
                $task->skipped += max(0, $task->total - $task->copied - $task->skipped - $task->failed);
                $task->fill(['status' => $task->failed > 0 ? 'failed' : 'completed', 'finished_at' => now()])->save();

                return;
            }
            try {
                $providers = $this->storages->providers();
                if (! ($providers[$task->source_disk]['enabled'] ?? false) || ! ($providers[$task->target_disk]['enabled'] ?? false)) {
                    throw new RuntimeException('storage_disabled');
                }
                if (! in_array($file->visibility, $providers[$task->target_disk]['visibilities'], true)) {
                    throw new RuntimeException('visibility_mismatch');
                }
                $result = $this->copy($file, $task->source_disk, $task->target_disk);
                $task->{$result}++;
            } catch (Throwable $exception) {
                $task->failed++;
                $errors = $task->errors ?? [];
                if (count($errors) < 32) {
                    $safeCodes = ['storage_disabled', 'visibility_mismatch', 'destination_conflict', 'source_checksum_mismatch', 'destination_checksum_mismatch'];
                    $errors[] = ['file_id' => $file->id, 'message' => in_array($exception->getMessage(), $safeCodes, true) ? $exception->getMessage() : 'file_copy_failed'];
                }
                $task->errors = $errors;
            }
            $task->last_file_id = $file->id;
            $task->save();
            FileSyncBatch::dispatch($id)->onConnection('file-sync')->onQueue('file-sync')->afterCommit();
        });
    }

    private function copy(File $file, string $sourceDisk, string $targetDisk): string
    {
        $sourcePath = $file->disk === $sourceDisk ? $file->path : $file->replicas()->where('disk', $sourceDisk)->sole()->path;
        $source = $this->storages->disk($sourceDisk);
        $target = $this->storages->disk($targetDisk);
        $targetPath = $file->path;
        $replica = $file->replicas()->where('disk', $targetDisk)->first();
        if ($replica) {
            $targetPath = $replica->path;
        }
        $alreadyExists = $target->exists($targetPath);
        if ($alreadyExists) {
            if ($this->checksum($target->readStream($targetPath)) !== $file->checksum) {
                throw new RuntimeException('destination_conflict');
            }
            $this->recordReplica($file, $targetDisk, $targetPath);

            return 'skipped';
        }
        if ($this->checksum($source->readStream($sourcePath)) !== $file->checksum) {
            throw new RuntimeException('source_checksum_mismatch');
        }
        $stream = $source->readStream($sourcePath);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read source file.');
        }
        try {
            if (! $target->writeStream($targetPath, $stream, ['visibility' => $file->visibility, 'mimetype' => $file->mime_type])) {
                throw new RuntimeException('Unable to copy file.');
            }
            if ($this->checksum($target->readStream($targetPath)) !== $file->checksum) {
                throw new RuntimeException('destination_checksum_mismatch');
            }
            $this->recordReplica($file, $targetDisk, $targetPath);
        } catch (Throwable $exception) {
            try {
                $target->delete($targetPath);
            } catch (Throwable) {
                // Keep the original exception; retry will verify any remaining object.
            }
            throw $exception;
        } finally {
            fclose($stream);
        }

        return 'copied';
    }

    private function recordReplica(File $file, string $disk, string $path): void
    {
        if ($file->disk !== $disk) {
            FileReplica::updateOrCreate(['file_id' => $file->id, 'disk' => $disk], ['path' => $path, 'checksum' => $file->checksum]);
        }
    }

    private function checksum(mixed $stream): string
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open stored file.');
        }
        try {
            $context = hash_init('sha256');
            if (hash_update_stream($context, $stream) === false) {
                throw new RuntimeException('Unable to checksum stored file.');
            }

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
