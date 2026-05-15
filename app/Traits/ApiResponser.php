<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponser
{
    /**
     * Return a standardized success response.
     *
     * @param  array{success: true, message: string, data: mixed}  $structure
     */
    protected function successResponse(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return a standardized error response.
     *
     * @param  array{success: false, message: string, errors: mixed|null}  $structure
     */
    protected function errorResponse(string $message, mixed $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Return a standardized paginated response using API Resources.
     *
     * @param  class-string<JsonResource>  $resourceClass
     * @param  array{success: true, message: string, data: mixed, meta: array{current_page: int, per_page: int, total_items: int, total_pages: int}}  $structure
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $resourceClass, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resourceClass::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total_items' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }
}
