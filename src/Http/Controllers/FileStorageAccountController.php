<?php

namespace Coding1688\Files\Http\Controllers;

use Coding1688\Files\Http\Requests\FileStorageAccountRequest;
use Coding1688\Files\Http\Resources\FileStorageAccountResource;
use Coding1688\Files\Management\FileStorageAccountService;
use Illuminate\Http\JsonResponse;

class FileStorageAccountController extends ManagementController
{
    public function __construct(private readonly FileStorageAccountService $service) {}

    public function list(): JsonResponse
    {
        return $this->success(FileStorageAccountResource::collection($this->service->list()));
    }

    public function settings(): JsonResponse
    {
        return $this->success($this->service->settings());
    }

    public function saveSettings(FileStorageAccountRequest $request): JsonResponse
    {
        return $this->success($this->service->saveSettings($request->validated()));
    }

    public function create(FileStorageAccountRequest $request): JsonResponse
    {
        return $this->success(new FileStorageAccountResource($this->service->create($request->validated())), status: 201);
    }

    public function update(int $id, FileStorageAccountRequest $request): JsonResponse
    {
        return $this->success(new FileStorageAccountResource($this->service->update($id, $request->validated())));
    }

    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success();
    }

    public function tasks(): JsonResponse
    {
        return $this->success($this->service->tasks());
    }

    public function sync(FileStorageAccountRequest $request): JsonResponse
    {
        return $this->success($this->service->sync($request->validated()), status: 202);
    }

    public function retry(int $id): JsonResponse
    {
        return $this->success($this->service->retry($id), status: 202);
    }
}
