<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPerformanceOverviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/admin/performance-overview
 *
 * Chart-ready aggregates for the four performance sections.
 */
class AdminPerformanceOverviewController extends Controller
{
    public function __construct(
        private readonly AdminPerformanceOverviewService $service
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $data = $this->service->overview(
            $validated['year'] ?? null,
            $validated['department_id'] ?? null,
        );

        return ApiResponse::success('Performance overview loaded successfully.', $data);
    }
}
