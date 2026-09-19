<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportExportRequest;
use App\Http\Requests\Reports\ReportRequest;
use App\Http\Resources\Api\V1\Reports\AchievementRowResource;
use App\Http\Resources\Api\V1\Reports\DepartmentPerformanceRowResource;
use App\Http\Resources\Api\V1\Reports\EmployeePerformanceRowResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use App\Services\Reports\AchievementReportService;
use App\Services\Reports\Concerns\ComputesReportMetrics;
use App\Services\Reports\Concerns\ResolvesReportScopes;
use App\Services\Reports\DepartmentPerformanceReportService;
use App\Services\Reports\EmployeePerformanceReportService;
use App\Services\Reports\PerformanceCalculationService;
use App\Services\Reports\ProgressTrendService;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportFilterBag;
use App\Services\Reports\TaskCompletionReportService;
use App\Services\Reports\TargetVsActualReportService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports & Analytics API (Phase 3.3-J).
 *
 * Every endpoint re-resolves the caller's department scope server-side and
 * wraps computed data in the reports envelope:
 *   { success, message, data, meta: { report_type, generated_at, filters } }
 * Exports are authenticated, re-authorized, rate-limited and audited.
 */
class ReportController extends Controller
{
    use ResolvesReportScopes;

    public function __construct(
        protected TaskCompletionReportService $taskCompletion,
        protected EmployeePerformanceReportService $employeePerformance,
        protected DepartmentPerformanceReportService $departmentPerformance,
        protected AchievementReportService $achievement,
        protected PerformanceCalculationService $performanceCalc,
        protected ProgressTrendService $progressTrend,
        protected TargetVsActualReportService $targetVsActual,
        protected ReportExportService $exporter,
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * GET /api/v1/reports/options — manager filter metadata.
     */
    public function options(Request $request): JsonResponse
    {
        $bag = new ReportFilterBag($request->query(), 'options');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);
        $bag->departmentId = $departmentId;

        $options = [
            'departments' => $this->departments($departmentId),
            'academic_years' => $this->academicYears(),
            'statuses' => ReportFilterBag::STATUSES,
            'priorities' => ReportFilterBag::PRIORITIES,
            'sortable' => ReportFilterBag::SORTABLE,
            'scopes' => ReportFilterBag::SCOPES,
            'max_per_page' => config('reports.max_per_page', 100),
            'trend_months' => config('reports.trend_months', 6),
            'top_performers_limit_max' => config('reports.top_performers_limit_max', 10),
        ];

        return $this->reportResponse('Report options retrieved successfully', $options, 'options', $bag);
    }

    /**
     * GET /api/v1/reports/me/performance — the caller's own KPI detail
     * (employees, and managers when they hold a linked employee record).
     */
    public function myPerformance(Request $request): JsonResponse
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            return ApiResponse::error('No linked employee profile for this account.', null, 404);
        }

        $bag = new ReportFilterBag($request->query(), 'employee-performance');

        $data = $this->employeePerformance->detail($request->user(), $employee, $bag);

        return $this->reportResponse('My performance retrieved successfully', $data, 'employee-performance', $bag);
    }

    /**
     * GET /api/v1/reports/task-completion.
     */
    public function taskCompletion(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'task-completion');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $data = $this->taskCompletion->report($request->user(), $bag, $departmentId);

        return $this->reportResponse('Task completion report retrieved successfully', $data, 'task-completion', $bag);
    }

    /**
     * GET /api/v1/reports/employees — paginated employee performance.
     */
    public function employeePerformance(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'employee-performance');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $paginator = $this->employeePerformance->table($request->user(), $bag, $departmentId);
        $rows = $this->resolvedRows(EmployeePerformanceRowResource::collection($paginator));

        return $this->tableResponse('Employee performance report retrieved successfully', $rows, 'employee-performance', $bag, $paginator);
    }

    /**
     * GET /api/v1/reports/employees/{employee} — single-employee drill-down.
     */
    public function employeePerformanceDetail(ReportRequest $request, Employee $employee): JsonResponse
    {
        $bag = $this->bag($request, 'employee-performance');
        $this->departmentScope($request->user(), $bag->departmentId);
        $this->assertEmployeeScope($request->user(), $employee);

        $data = $this->employeePerformance->detail($request->user(), $employee, $bag);

        return $this->reportResponse('Employee performance retrieved successfully', $data, 'employee-performance', $bag);
    }

    /**
     * GET /api/v1/reports/departments — paginated department performance.
     */
    public function departmentPerformance(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'department-performance');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $paginator = $this->departmentPerformance->table($request->user(), $bag, $departmentId);
        $rows = $this->resolvedRows(DepartmentPerformanceRowResource::collection($paginator));

        return $this->tableResponse('Department performance report retrieved successfully', $rows, 'department-performance', $bag, $paginator);
    }

    /**
     * GET /api/v1/reports/departments/{department} — single-department drill-down.
     */
    public function departmentPerformanceDetail(ReportRequest $request, Department $department): JsonResponse
    {
        $bag = $this->bag($request, 'department-performance');

        // The requested department must fall inside the caller's scope
        // (dept heads are locked to their own department → 403 otherwise).
        $this->departmentScope($request->user(), $department->id);

        $data = $this->departmentPerformance->detail($request->user(), $department, $bag);

        return $this->reportResponse('Department performance retrieved successfully', $data, 'department-performance', $bag);
    }

    /**
     * GET /api/v1/reports/achievements/monthly — ranked leaderboard.
     */
    public function achievementsMonthly(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'achievements');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $report = $this->achievement->monthly($request->user(), $bag, $departmentId);
        $report['rankings'] = $this->resolvedRows(AchievementRowResource::collection($report['rankings']));

        return $this->reportResponse('Monthly achievement report retrieved successfully', $report, 'achievements', $bag);
    }

    /**
     * GET /api/v1/reports/achievements/employees/{employee} — personal series.
     */
    public function achievementsEmployee(ReportRequest $request, Employee $employee): JsonResponse
    {
        $bag = $this->bag($request, 'achievements');
        $this->assertEmployeeScope($request->user(), $employee);

        $data = $this->achievement->employee($request->user(), $employee, $bag);

        return $this->reportResponse('Employee achievements retrieved successfully', $data, 'achievements', $bag);
    }

    /**
     * GET /api/v1/reports/target-vs-actual.
     */
    public function targetVsActual(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'target-vs-actual');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $data = $this->targetVsActual->report($request->user(), $bag, $departmentId);

        return $this->reportResponse('Target vs actual report retrieved successfully', $data, 'target-vs-actual', $bag);
    }

    /**
     * GET /api/v1/reports/trend/monthly-completions.
     */
    public function trend(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'trend');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $data = $this->progressTrend->trend($request->user(), $bag, $departmentId);

        return $this->reportResponse('Progress trend retrieved successfully', $data, 'trend', $bag);
    }

    /**
     * GET /api/v1/reports/top-performers.
     */
    public function topPerformers(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'top-performers');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $data = $this->progressTrend->topPerformers($request->user(), $bag, $departmentId);

        return $this->reportResponse('Top performers retrieved successfully', $data, 'top-performers', $bag);
    }

    /**
     * GET /api/v1/reports/export/task-completion.{?format}
     */
    public function exportTaskCompletion(ReportExportRequest $request): StreamedResponse
    {
        $bag = $this->bag($request, 'task-completion');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $report = $this->taskCompletion->report($request->user(), $bag, $departmentId);

        $columns = ['Metric', 'Value'];
        $rows = [
            ['Metric' => 'Period', 'Value' => $report['period']],
            ['Metric' => 'Total tasks', 'Value' => $report['total_tasks']],
            ['Metric' => 'Completed tasks', 'Value' => $report['completed_tasks']],
            ['Metric' => 'Pending tasks', 'Value' => $report['pending_tasks']],
            ['Metric' => 'Overdue tasks', 'Value' => $report['overdue_tasks']],
            ['Metric' => 'Submitted tasks', 'Value' => $report['submitted_tasks']],
            ['Metric' => 'Rejected tasks', 'Value' => $report['rejected_tasks']],
            ['Metric' => 'Cancelled tasks', 'Value' => $report['cancelled_tasks']],
            ['Metric' => 'Completed on time', 'Value' => $report['on_time']],
            ['Metric' => 'Completed late', 'Value' => $report['late']],
            ['Metric' => 'Completion rate (%)', 'Value' => $report['completion_rate']],
            ['Metric' => 'On-time rate (%)', 'Value' => $report['on_time_rate']],
        ];

        return $this->export($request->user(), 'task-completion', 'Task_Completion', $columns, $rows, $bag);
    }

    /**
     * GET /api/v1/reports/export/employee-performance.{?format}
     */
    public function exportEmployeePerformance(ReportExportRequest $request): StreamedResponse
    {
        $bag = $this->bag($request, 'employee-performance');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $rows = $this->sliced($this->employeePerformance->rows($request->user(), $bag, $departmentId));
        $columns = [
            'rank', 'employee_name', 'department_name', 'total_tasks', 'completed_tasks',
            'pending_tasks', 'overdue_tasks', 'on_time', 'late', 'completion_rate', 'on_time_rate', 'next_due_date',
        ];

        return $this->export($request->user(), 'employee-performance', 'Employee_Performance', $columns, $rows, $bag);
    }

    /**
     * GET /api/v1/reports/export/department-performance.{?format}
     */
    public function exportDepartmentPerformance(ReportExportRequest $request): StreamedResponse
    {
        $bag = $this->bag($request, 'department-performance');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $rows = $this->sliced($this->departmentPerformance->rows($request->user(), $bag, $departmentId));
        $columns = [
            'rank', 'department_name', 'department_code', 'employee_count', 'total_tasks',
            'completed_tasks', 'pending_tasks', 'overdue_tasks', 'on_time', 'late',
            'completion_rate', 'on_time_rate', 'next_due_date',
        ];

        return $this->export($request->user(), 'department-performance', 'Department_Performance', $columns, $rows, $bag);
    }

    /**
     * GET /api/v1/reports/export/achievements.{?format}
     * Employees are forced to their own record; managers export in scope.
     */
    public function exportAchievements(ReportExportRequest $request): StreamedResponse
    {
        $bag = new ReportFilterBag($request->query(), 'achievements');

        $employeeId = $bag->employeeId;
        if ($request->user()?->roleName() === 'employee') {
            $employeeId = (int) ($request->user()?->employee?->id ?? 0);
            if ($employeeId === 0) {
                abort(403, 'Forbidden');
            }
        }

        if ($employeeId !== null) {
            $employee = Employee::query()->find($employeeId);
            if ($employee === null) {
                abort(404, 'Employee not found.');
            }
            $this->assertEmployeeScope($request->user(), $employee);
            // Personal export: single-row leaderboard context is misleading,
            // so export the achievements monthly detail for that employee.
            $bag->employeeId = $employeeId;

            $data = $this->achievement->employee($request->user(), $employee, $bag);

            $columns = ['employee_name', 'period', 'current_rank', 'completed_tasks', 'completion_rate', 'on_time_rate', 'overall_score'];
            $rows = array_map(function (array $month) use ($data) {
                return [
                    'employee_name' => $data['employee_name'],
                    'period' => $month['label'],
                    'current_rank' => $data['current_rank'] ?? '',
                    'completed_tasks' => $month['completed_tasks'],
                    'completion_rate' => $month['completion_rate'],
                    'on_time_rate' => $month['on_time_rate'],
                    'overall_score' => $month['overall_score'],
                ];
            }, $data['series']);

            return $this->export($request->user(), 'achievements', 'Achievements', $columns, $rows, $bag);
        }

        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);
        $report = $this->achievement->monthly($request->user(), $bag, $departmentId);

        $columns = ['rank', 'employee_name', 'department_name', 'completed_tasks', 'total_tasks', 'completion_rate', 'on_time_rate', 'overall_score'];
        $rows = $this->sliced($report['rankings']);

        return $this->export($request->user(), 'achievements', 'Achievements', $columns, $rows, $bag);
    }

    /**
     * Stream + audit a report export.
     *
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function export(User $user, string $type, string $label, array $columns, array $rows, ReportFilterBag $bag): StreamedResponse
    {
        $format = $bag->format;
        $date = now()->format('Y-m-d');
        $filename = "JPC_{$label}_Report_{$date}.{$format}";

        $this->activityLog->log(
            $user,
            'report.export',
            null,
            "{$user->name} exported the {$label} report ({$format})",
            [
                'report_type' => $type,
                'format' => $format,
                'row_count' => count($rows),
                'filters' => $bag->meta(),
            ],
        );

        return $this->exporter->stream($columns, $rows, $format, $filename);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sliced(array $rows): array
    {
        $max = (int) config('reports.export_rows_max', 5000);

        return array_slice($rows, 0, $max);
    }

    private function bag(ReportRequest $request, string $type): ReportFilterBag
    {
        return new ReportFilterBag($request->validated(), $type);
    }

    private function resolvedRows(mixed $collection): array
    {
        $resolved = $collection->resolve(request());
        $rows = is_array($resolved) && array_is_list($resolved)
            ? $resolved
            : (is_array($resolved) ? ($resolved['data'] ?? []) : []);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function departments(?int $departmentId): array
    {
        $query = \App\Models\Department::query()->active()->orderBy('name');

        if ($departmentId !== null) {
            $query->where('id', $departmentId);
        }

        return $query->get(['id', 'name'])
            ->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, status: string}>
     */
    private function academicYears(): array
    {
        return \App\Models\AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status'])
            ->map(fn ($year) => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reportResponse(string $message, array $data, string $type, ReportFilterBag $bag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'report_type' => $type,
                'generated_at' => now()->toIso8601String(),
                'filters' => $bag->meta(),
            ],
        ]);
    }

    /**
     * GET /api/v1/reports/overview — analytics overview KPIs.
     */
    public function overview(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'analytics-overview');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $data = $this->performanceCalc->overview($request->user(), $bag, $departmentId);

        return $this->reportResponse('Analytics overview retrieved successfully', $data, 'analytics-overview', $bag);
    }

    /**
     * GET /api/v1/reports/leaderboard — employee leaderboard.
     */
    public function leaderboard(ReportRequest $request): JsonResponse
    {
        $bag = $this->bag($request, 'leaderboard');
        $departmentId = $this->departmentScope($request->user(), $bag->departmentId);

        $data = $this->performanceCalc->leaderboard($request->user(), $bag, $departmentId);

        return $this->reportResponse('Leaderboard retrieved successfully', $data, 'leaderboard', $bag);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function tableResponse(string $message, array $rows, string $type, ReportFilterBag $bag, LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $rows,
            'meta' => [
                'report_type' => $type,
                'generated_at' => now()->toIso8601String(),
                'filters' => $bag->meta(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }
}