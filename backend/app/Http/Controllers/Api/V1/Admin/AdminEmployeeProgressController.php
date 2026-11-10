<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminEmployeeProgressService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEmployeeProgressController extends Controller
{
    public function __construct(private readonly AdminEmployeeProgressService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->service->paginate($filters);

        return ApiResponse::success('Employee progress loaded successfully.', [
            'summary' => $this->service->summary(),
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::success('Employee progress summary loaded successfully.', $this->service->summary());
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success('Employee progress options loaded successfully.', $this->service->options());
    }

    public function show(int $employeeId): JsonResponse
    {
        return ApiResponse::success('Employee progress detail loaded successfully.', $this->service->detail($employeeId));
    }
}
