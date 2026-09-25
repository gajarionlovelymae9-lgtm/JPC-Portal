<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * HR Portal — HR dashboard (KPI cards, monitoring, diagnostics).
 *
 * Phase 3.3-W. Every endpoint delegates to HrDashboardService so the
 * authorization/aggregation contract stays in ONE verified service. HR must
 * never reach Admin settings/AI config/OCR config/system config — enforced by
 * the `hr.dashboard` permission + the absence of `settings.*` in hr.* policy.
 */
class HrDashboardController extends Controller
{
    public function __construct(private readonly HrDashboardService $hrDashboardService)
    {
    }

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success(
            'HR dashboard loaded',
            $this->hrDashboardService->kpis()
        );
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success(
            'HR dashboard options loaded',
            $this->hrDashboardService->options()
        );
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::success(
            'HR summary loaded',
            $this->hrDashboardService->summary()
        );
    }

    public function employees(): JsonResponse
    {
        return ApiResponse::success(
            'Employees loaded',
            $this->hrDashboardService->employees()
        );
    }

    public function performance(): JsonResponse
    {
        return ApiResponse::success(
            'Department performance loaded',
            $this->hrDashboardService->performance()
        );
    }

    public function tasks(): JsonResponse
    {
        return ApiResponse::collection(
            'Tasks loaded',
            $this->hrDashboardService->tasks()
        );
    }

    public function reports(): JsonResponse
    {
        return ApiResponse::collection(
            'Reports loaded',
            $this->hrDashboardService->reports()
        );
    }

    public function achievements(): JsonResponse
    {
        return ApiResponse::collection(
            'Achievements loaded',
            $this->hrDashboardService->achievements()
        );
    }

    public function audit(): JsonResponse
    {
        return ApiResponse::collection(
            'Audit entries loaded',
            $this->hrDashboardService->audit()
        );
    }
}
