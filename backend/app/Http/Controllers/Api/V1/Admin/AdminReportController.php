<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Admin\AdminReportCenterService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminReportCenterService $reportService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->reportService->listReports($request->all());

        return ApiResponse::success('Reports loaded successfully.', $data);
    }

    public function show(Report $report): JsonResponse
    {
        $data = $this->reportService->showReport($report);

        return ApiResponse::success('Report details loaded successfully.', $data);
    }

    public function override(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,revision_required,rejected',
            'reason' => 'required|string|max:1000',
        ]);

        $res = $this->reportService->humanOverride($request->user(), $report, $validated);

        return ApiResponse::success('Report override recorded successfully.', $res);
    }
}
