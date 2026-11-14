<?php

namespace Coding1688\Files\Http\Controllers;

use Coding1688\Files\Http\Requests\FileRequest;
use Coding1688\Files\Http\Resources\FileResource;
use Coding1688\Files\Management\FileService;
use Illuminate\Http\JsonResponse;

class FileController extends ManagementController
{
    public function __construct(private FileService $service) {}

    public function list(FileRequest $request): JsonResponse
    {
        $page = $this->service->list($request->validated());

        return $this->paginate($page, FileResource::collection($page));
    }

    public function options(): JsonResponse
    {
        return $this->success($this->service->options());
    }

    public function detail(int $id): JsonResponse
    {
        return $this->success(new FileResource($this->service->detail($id)));
    }

    public function upload(FileRequest $request): JsonResponse
    {
        return $this->success(FileResource::collection($this->service->upload($request->validated())), status: 201);
    }

    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success();
    }
}
