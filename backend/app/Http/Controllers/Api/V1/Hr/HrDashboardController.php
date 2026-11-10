<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Services\Hr\HrDashboardService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HR Portal — HR dashboard (KPI cards, monitoring, directory, achievements).
 *
 * Phase 3.3-W. Every endpoint delegates to HrDashboardService so the
 * authorization/aggregation contract stays in ONE verified service. HR must
 * never reach Admin settings/AI config/OCR config/system config — enforced by
 * the `hr.dashboard.view` permission on the route group plus the absence of any
 * `settings.*` permission in the HR role.
 *
 * Note on `summary()`: it used to exist as a separate route but had no service
 * implementation, so `/hr/summary` was a guaranteed 500. The KPI block returned
 * by `dashboard()` already IS the summary (counts + rates + 6-month trends), so
 * the duplicate endpoint was retired rather than given a second, divergent
 * definition of the same numbers.
 */
class HrDashboardController extends Controller
{
    public function __construct(private readonly HrDashboardService $hrDashboardService) {}

    /**
     * KPI header block for the dashboard: employee/task/report counts, the three
     * rates, and the multi-month trend series used by the charts.
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success(
            'HR dashboard loaded',
            $this->hrDashboardService->kpis()
        );
    }

    /**
     * Every filter option the HR page needs, in one call.
     */
    public function options(): JsonResponse
    {
        return ApiResponse::success(
            'HR dashboard options loaded',
            $this->hrDashboardService->options()
        );
    }

    /**
     * Employee Progress Overview.
     */
    public function employees(Request $request): JsonResponse
    {
        $filters = $this->filters($request, [
            'department_id', 'position_id', 'status', 'account_status', 'search',
            'from_date', 'to_date', 'sort', 'page', 'per_page',
        ], HrDashboardService::EMPLOYMENT_STATUSES);

        return $this->paged(
            'Employees loaded',
            $this->hrDashboardService->employees($filters),
            $this->hrDashboardService->employeeSummary($filters)
        );
    }

    /**
     * Task Progress — the monitoring table of live assignments and their
     * progress, deadlines and report status.
     */
    public function monitoring(Request $request): JsonResponse
    {
        $filters = $this->filters($request, [
            'department_id', 'employee_id', 'status', 'overdue',
            'search', 'from_date', 'to_date', 'page', 'per_page',
        ], array_keys(HrDashboardService::TASK_STATUSES));

        return $this->paged(
            'Task monitoring loaded',
            $this->hrDashboardService->tasks($filters),
            $this->hrDashboardService->taskSummary($filters)
        );
    }

    /**
     * Report Monitoring. `?late=1` narrows the same query to the Late Reports
     * alert table rather than introducing a second code path.
     */
    public function reports(Request $request): JsonResponse
    {
        $filters = $this->filters($request, [
            'department_id', 'employee_id', 'status', 'late', 'search',
            'from_date', 'to_date', 'page', 'per_page',
        ], HrDashboardService::reportStatusValues());

        return $this->paged(
            'Reports loaded',
            $this->hrDashboardService->reports($filters),
            $this->hrDashboardService->reportSummary($filters)
        );
    }

    /**
     * Monthly Employee Achievement, with each employee's real badge grants.
     */
    public function achievements(Request $request): JsonResponse
    {
        $filters = $this->filters($request, [
            'department_id', 'year', 'month', 'search', 'page', 'per_page',
        ]);

        return $this->paged(
            'Achievements loaded',
            $this->hrDashboardService->achievements($filters)
        );
    }

    /**
     * Keep only the filters a caller is allowed to pass, so a stray query
     * string can never reach the query builder.
     *
     * `status` is scoped per endpoint because it means three different columns
     * here (employment, assignment, report). An unknown value would otherwise
     * filter to a silently empty table, which reads as "no such data exists"
     * rather than "that status is not valid here" — a 422 is the honest answer.
     *
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $statusValues
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $allowed, array $statusValues = []): array
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => array_filter([
                'nullable',
                'string',
                'max:40',
                $statusValues === [] ? null : Rule::in($statusValues),
            ], fn ($rule) => $rule !== null),
            // Directory-only filter: `account_status` narrows `users.status`,
            // not `employees.employment_status`.
            'account_status' => ['nullable', Rule::enum(UserStatus::class)],
            'late' => ['nullable', 'in:1'],
            'overdue' => ['nullable', 'in:1'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'search' => ['nullable', 'string', 'max:120'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'sort' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return array_intersect_key($validated, array_flip($allowed));
    }

    /**
     * Paged list payload: `items` + `meta` + the section's `summary`, matching
     * the Admin list endpoints so the frontend can share one table component.
     *
     * @param  array<string, mixed>  $summary
     */
    private function paged(string $message, LengthAwarePaginator $paginator, array $summary = []): JsonResponse
    {
        return ApiResponse::success($message, [
            // Cast so an empty summary serialises as `{}` rather than `[]`.
            'summary' => (object) $summary,
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
