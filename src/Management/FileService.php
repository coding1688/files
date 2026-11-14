<?php

namespace Coding1688\Files\Management;

use Coding1688\Files\Constants\FileType;
use Coding1688\Files\FileStorage;
use Coding1688\Files\ModelResolver;
use Coding1688\Files\Models\File;
use Coding1688\Files\UploadConfiguration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class FileService
{
    public function __construct(private FileStorage $fileStorage, private UploadConfiguration $uploads) {}

    public function list(array $validated): LengthAwarePaginator
    {
        $query = (ModelResolver::resolve('file'))::query()->when(method_exists(new (ModelResolver::resolve('file')), 'uploader'), fn ($query) => $query->with('uploader:id,name'))->withCount('references')->latest('id');
        if ($q = $validated['q'] ?? null) {
            $query->where(fn ($query) => $query->where('original_name', 'like', '%'.$q.'%')->orWhere('path', 'like', '%'.$q.'%'));
        }
        if ($disk = $validated['disk'] ?? null) {
            $query->where('disk', $disk);
        }
        if ($type = $validated['type'] ?? null) {
            $query->where('type', $type);
        }
        if ($mime = $validated['mime'] ?? null) {
            $query->where('mime_type', 'like', $mime.'%');
        }

        return $query->paginate(20, ['*'], 'page', (int) ($validated['page'] ?? 1));
    }

    /** @return array{providers: array<string, array{provider: string, enabled: bool, visibilities: list<string>}>, default_disk: string, max_size_kb: int, max_files: int} */
    public function options(): array
    {
        return [
            'providers' => $this->uploads->providers(),
            'types' => FileType::options(),
            'default_disk' => $this->uploads->defaultDisk(),
            'allowed_extensions' => $this->uploads->allowedExtensions(),
            'max_size_kb' => $this->uploads->maxSizeKb(),
            'max_files' => 16,
        ];
    }

    public function detail(int $id): File
    {
        return (ModelResolver::resolve('file'))::with(['references', 'replicas'])->when(method_exists(new (ModelResolver::resolve('file')), 'uploader'), fn ($query) => $query->with('uploader:id,name'))->withCount('references')->findOrFail($id);
    }

    /**
     * @param  array{files: list<UploadedFile>, disk: string, type: string, visibility: string}  $validated
     * @return Collection<int, File>
     *
     * @throws Throwable
     */
    public function upload(array $validated): Collection
    {
        $this->guard('upload');

        return $this->store($validated);
    }

    /** @param array{files: list<UploadedFile>, disk: string, type: string, visibility: string} $validated */
    public function store(array $validated): Collection
    {
        $disk = $validated['disk'];
        $directory = 'uploads/'.$validated['type'].'/'.now()->format('Y/m/d');
        $writtenPaths = [];
        $records = collect();

        $connection = ModelResolver::connection(['file', 'reference', 'replica']);
        $connection->beginTransaction();
        try {
            foreach ($validated['files'] as $file) {
                $stored = $this->fileStorage->store($file, $disk, $directory, $validated['visibility']);
                $writtenPaths[] = $stored->path;
                $records->push((ModelResolver::resolve('file'))::create($stored->toArray() + [
                    'disk' => $disk,
                    'type' => $validated['type'],
                    'visibility' => $validated['visibility'],
                    'created_by' => app(ManagementContext::class)->actorId(),
                ]));
            }
            app(ManagementContext::class)->audit('file.item.upload', null, ['disk' => $disk, 'type' => $validated['type'], 'files' => $records->pluck('id')->all()]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            foreach ($writtenPaths as $path) {
                try {
                    $this->fileStorage->delete($disk, $path);
                } catch (Throwable) { /* Preserve the original failure. */
                }
            }
            throw $exception;
        }

        return $records->each(function (File $file): void {
            if (method_exists($file, 'uploader')) {
                $file->load('uploader:id,name');
            }
        });
    }

    public function delete(int $id): void
    {
        $this->guard('delete');
        ModelResolver::connection(['file', 'reference', 'replica'])->transaction(function () use ($id): void {
            $model = (ModelResolver::resolve('file'))::lockForUpdate()->findOrFail($id);
            if ($model->references()->exists()) {
                throw ValidationException::withMessages(['file' => __('files::file.in_use')]);
            }
            foreach ($model->replicas as $replica) {
                $this->fileStorage->delete($replica->disk, $replica->path);
            }
            $this->fileStorage->delete($model->disk, $model->path);
            app(ManagementContext::class)->audit('file.item.delete', (string) $id, ['disk' => $model->disk, 'path' => $model->path]);
            $model->delete();
        });
    }

    private function guard(string $action): void
    {
        app(ManagementContext::class)->authorize('file.item.'.$action);
    }
}
