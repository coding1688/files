<?php

namespace Coding1688\Files\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

abstract class ManagementController
{
    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json($data === null ? (object) [] : ['data' => $data], $status);
    }

    protected function paginate(LengthAwarePaginator $page, mixed $data): JsonResponse
    {
        return response()->json(['data' => $data, 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }
}
