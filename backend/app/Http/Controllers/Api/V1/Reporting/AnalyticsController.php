<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ComparisonRequest;
use App\Http\Requests\Reporting\ReportFilterRequest;
use App\Services\Reporting\AnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Phase 9 — Analytics API (spec items 14, 16-18).
 *
 * Trends, comparisons, rankings and rating distributions. Route-level
 * `analytics.view` + `throttle:reports`; deep authorization and per-role
 * scoping handled by AnalyticsService (ResolvesReportingScope).
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function performanceTrend(ReportFilterRequest $request): JsonResponse
    {
        $months = (int) $request->input('months', config('reports.trend_months', 6));

        return ApiResponse::success(
            'Performance trend generated successfully',
            $this->analyticsService->getPerformanceTrend($request->user(), $request->validated(), $months),
        );
    }

    public function completionTrend(ReportFilterRequest $request): JsonResponse
    {
        $months = (int) $request->input('months', config('reports.trend_months', 6));

        return ApiResponse::success(
            'Completion trend generated successfully',
            $this->analyticsService->getCompletionTrend($request->user(), $request->validated(), $months),
        );
    }

    public function submissionTrend(ReportFilterRequest $request): JsonResponse
    {
        $months = (int) $request->input('months', config('reports.trend_months', 6));

        return ApiResponse::success(
            'Submission trend generated successfully',
            $this->analyticsService->getSubmissionTrend($request->user(), $request->validated(), $months),
        );
    }

    public function departmentComparison(ComparisonRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->analyticsService->getDepartmentComparison(
                array_map('intval', $validated['ids'] ?? []),
                $validated,
                $request->user(),
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to run this comparison', null, 403);
        }

        return ApiResponse::success('Department comparison generated successfully', $result);
    }

    public function employeeComparison(ComparisonRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->analyticsService->getEmployeeComparison(
                array_map('intval', $validated['ids'] ?? []),
                $validated,
                $request->user(),
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to run this comparison', null, 403);
        }

        return ApiResponse::success('Employee comparison generated successfully', $result);
    }

    public function periodComparison(ComparisonRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->analyticsService->getPeriodComparison(
                (string) ($validated['period_a'] ?? ''),
                (string) ($validated['period_b'] ?? ''),
                $request->user(),
                $validated,
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ApiResponse::error('Unauthorized to run this comparison', null, 403);
        }

        return ApiResponse::success('Period comparison generated successfully', $result);
    }

    public function rankings(ReportFilterRequest $request): JsonResponse
    {
        $metric = (string) $request->input('metric', 'productivity');

        return ApiResponse::success(
            'Rankings generated successfully',
            $this->analyticsService->getRankings($request->user(), $request->validated(), $metric),
        );
    }

    public function performanceDistribution(ReportFilterRequest $request): JsonResponse
    {
        return ApiResponse::success(
            'Performance distribution generated successfully',
            $this->analyticsService->getPerformanceDistribution($request->user(), $request->validated()),
        );
    }
}