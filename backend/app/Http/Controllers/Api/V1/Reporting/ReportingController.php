<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ComparisonRequest;
use App\Http\Requests\Reporting\ReportFilterRequest;
use App\Services\Reporting\AnalyticsService;
use App\Services\Reporting\ManagementSummaryService;
use App\Services\Reporting\ReportingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Phase 9 — Reporting API (spec items 7-13, 15, 19-20).
 *
 * Thin HTTP layer: validation happens in the FormRequest, authorization and
 * server-side scoping inside the services (ResolvesReportingScope). Endpoints
 * in this controller are permission-guarded at the route level (reports.view
 * for self-service, analytics.view for management reports).
 */
class ReportingController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
        private readonly AnalyticsService $analyticsService,
        private readonly ManagementSummaryService $managementSummaryService,
    ) {}

    /**
     * Employee progress report — locked to the caller's own record for
     * employees, to their own department for department heads.
     */
    public function employee(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $employeeId = (int) $request->input('employee_id', $user->employee?->id);

        if ($employeeId <= 0) {
            return ApiResponse::error('Employee ID is required', null, 422);
        }

        try {
            $report = $this->reportingService->generateEmployeeReport($employeeId, $request->validated(), $user);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to view this employee report', null, 403);
        }

        return ApiResponse::success('Employee progress report generated successfully', $report);
    }

    /**
     * Department performance report — dept heads are forced to their own
     * department by the service (requesting another → 403).
     */
    public function department(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $deptId = (int) $request->input('department_id', $user->employee?->department_id);

        if ($deptId <= 0) {
            return ApiResponse::error('Department ID is required', null, 422);
        }

        try {
            $report = $this->reportingService->generateDepartmentReport($deptId, $request->validated(), $user);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to view other department reports', null, 403);
        }

        return ApiResponse::success('Department performance report generated successfully', $report);
    }

    public function institution(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $report = $this->reportingService->generateInstitutionReport($request->validated(), $user);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to view institutional reports', null, 403);
        }

        return ApiResponse::success('Institution performance report generated successfully', $report);
    }

    public function monthly(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) $request->input('year', date('Y'));
        $month = (int) $request->input('month', date('n'));

        try {
            $report = $this->reportingService->generateMonthlyReport($year, $month, $user);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to view monthly performance reports', null, 403);
        }

        return ApiResponse::success('Monthly performance report generated successfully', $report);
    }

    public function achievements(ReportFilterRequest $request): JsonResponse
    {
        try {
            $report = $this->reportingService->generateAchievementReport($request->validated(), $request->user());
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to view achievement reports', null, 403);
        }

        return ApiResponse::success('Achievement summary report generated successfully', $report);
    }

    public function late(ReportFilterRequest $request): JsonResponse
    {
        $report = $this->reportingService->generateLateReport($request->validated(), $request->user());

        return ApiResponse::success('Late submission report generated successfully', $report);
    }

    public function pending(ReportFilterRequest $request): JsonResponse
    {
        $report = $this->reportingService->generatePendingReport($request->validated(), $request->user());

        return ApiResponse::success('Pending reports analytics generated successfully', $report);
    }

    public function targets(ReportFilterRequest $request): JsonResponse
    {
        $report = $this->reportingService->generateTargetReport($request->validated(), $request->user());

        return ApiResponse::success('Target achievement report generated successfully', $report);
    }

    public function dataQuality(ReportFilterRequest $request): JsonResponse
    {
        $report = $this->reportingService->generateDataQualityReport($request->validated(), $request->user());

        return ApiResponse::success('Data quality report generated successfully', $report);
    }

    /**
     * Management summary — grounded deterministic narrative (admin/hr only).
     */
    public function managementSummary(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) $request->input('year', date('Y'));
        $month = (int) $request->input('month', date('n'));

        try {
            $summary = $this->managementSummaryService->generateSummary($year, $month, $user);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to view management summaries', null, 403);
        }

        return ApiResponse::success('Management summary generated successfully', $summary);
    }

    /**
     * Comparison endpoint (employee / department / period). Analytics-scoped:
     * employees may only compare themselves; dept heads only their department.
     */
    public function comparison(ComparisonRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $type = $validated['comparison_type'];
        $filters = $validated['filters'] ?? $validated;

        try {
            $result = match ($type) {
                'employee' => $this->analyticsService->getEmployeeComparison(
                    array_map('intval', $validated['ids'] ?? []),
                    $filters,
                    $user,
                ),
                'department' => $this->analyticsService->getDepartmentComparison(
                    array_map('intval', $validated['ids'] ?? []),
                    $filters,
                    $user,
                ),
                'period' => $this->analyticsService->getPeriodComparison(
                    (string) ($validated['period_a'] ?? ''),
                    (string) ($validated['period_b'] ?? ''),
                    $user,
                    $filters,
                ),
                default => throw new \InvalidArgumentException('Unsupported comparison type'),
            };
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to run this comparison', null, 403);
        }

        return ApiResponse::success('Comparison generated successfully', [
            'comparison_type' => $type,
            'result' => $result,
        ]);
    }
}