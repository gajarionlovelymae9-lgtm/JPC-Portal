<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminMonitoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Task monitoring, report monitoring and the institution calendar.
 *
 *   GET /api/v1/admin/task-monitoring
 *   GET /api/v1/admin/report-monitoring
 *   GET /api/v1/admin/calendar
 *   GET /api/v1/admin/monitoring-options
 */
class AdminMonitoringController extends Controller
{
    public function __construct(
        private readonly AdminMonitoringService $service
    ) {}

    public function tasks(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'priority' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:30'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'overdue' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->service->tasks($filters);

        return ApiResponse::success('Task monitoring loaded successfully.', [
            'items' => $paginator->items(),
            'summary' => $this->service->taskSummary($filters),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'ai_result' => ['nullable', 'string', 'in:scored,unscored'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->service->reports($filters);

        return ApiResponse::success('Report monitoring loaded successfully.', [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return ApiResponse::success('Calendar loaded successfully.', [
            'events' => $this->service->calendar($filters),
            'sources' => $this->service->calendarSources(),
            'range' => [
                'from' => $filters['from'] ?? now()->startOfMonth()->toDateString(),
                'to' => $filters['to'] ?? now()->endOfMonth()->toDateString(),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success('Monitoring options loaded successfully.', $this->service->options());
    }
}
