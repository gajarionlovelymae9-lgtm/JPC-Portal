<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * App\Support\ApiResponse
 *
 * Standardized JSON envelope for every JSON API response exposed by JPC Portal.
 *
 * Conventions:
 *   success:    { success: true,  message: ...,  data: {...} }
 *   collection: { success: true,  message: ...,  data: [...],  meta: {...} }
 *   error:      { success: false, message: ...,  errors: {...} }
 */
final class ApiResponse
{
    /**
     * Build a success response.
     */
    public static function success(string $message = 'Request successful', mixed $data = [], int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Build a success response for a resource collection.
     *
     * `meta` is emitted as a sibling of `data` (not nested inside it) and is
     * only present when the collection is backed by a paginator.
     */
    public static function collection(string $message, ResourceCollection $resource, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $resource->resolve(request()),
        ];

        $paginator = $resource->resource;

        if ($paginator instanceof LengthAwarePaginator) {
            $payload['meta'] = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ];
        }

        return response()->json($payload, $status);
    }

    /**
     * Build a success response for created resources.
     */
    public static function created(string $message = 'Resource created successfully', mixed $data = [], int $status = JsonResponse::HTTP_CREATED): JsonResponse
    {
        return self::success($message, $data, $status);
    }

    /**
     * Build an error response.
     *
     * @param  array<string, mixed>|mixed  $errors
     */
    public static function error(
        string $message = 'An unexpected error occurred',
        mixed $errors = null,
        int $status = JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Build a validation error response (HTTP 422).
     *
     * @param  array<string, mixed>|mixed  $errors
     */
    public static function validationError(string $message = 'Validation failed', mixed $errors = [], int $status = JsonResponse::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return self::error($message, $errors, $status);
    }

    /**
     * Build a "no content" response (HTTP 204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
