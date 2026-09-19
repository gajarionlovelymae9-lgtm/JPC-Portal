<?php

namespace App\Http\Controllers\Api\V1\DepartmentHead;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\DepartmentHead\DepartmentReportReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentReportController extends Controller
{
    public function __construct(
        private readonly DepartmentReportReviewService $reportService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['department_id', 'status', 'search']);
        $reports = $this->reportService->listReports($request->user(), $filters);

        return ApiResponse::success('Department reports loaded successfully.', $reports);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        $data = $this->reportService->showReport($request->user(), $report);

        return ApiResponse::success('Report details loaded successfully.', $data);
    }

    public function review(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:approved,approve,revision_required,revision,rejected,reject',
            'comments' => 'nullable|string|max:2000',
        ]);

        $res = $this->reportService->submitReview($request->user(), $report, $validated);

        return ApiResponse::success('Report review recorded successfully.', $res);
    }
}
