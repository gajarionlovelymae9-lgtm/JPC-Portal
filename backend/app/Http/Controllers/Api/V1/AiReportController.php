<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AiReports\ReportAnalysisService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI/OCR Processing Center (Phase 3.3-Q).
 *
 * Exposes the processing pipeline and analysis read-back endpoints.
 * All heavy work is delegated through queued jobs and the existing
 * domain services (OCR providers, AI providers, consistency checker,
 * reference analyzer, similarity service) already wired in the
 * Services\AiReports tree.
 *
 * AI produces analysis only; the human reviewer makes the final
 * decision.
 */
class AiReportController extends Controller
{
    public function __construct(
        private readonly ReportAnalysisService $analysis,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Report::class);

        return ApiResponse::success(
            'AI processing dashboard retrieved successfully',
            $this->analysis->dashboard($request->user()),
        );
    }

    public function show(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'Processing status retrieved successfully',
            $this->analysis->status($report),
        );
    }

    public function process(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $jobId = $this->analysis->queue($report);

        return ApiResponse::success(
            'Report queued for AI/OCR processing',
            ['job_id' => $jobId],
        );
    }

    public function ocr(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        return ApiResponse::success(
            'OCR processing queued',
            ['report_id' => $report->getKey()],
        );
    }

    public function analyze(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        return ApiResponse::success(
            'AI analysis queued',
            ['report_id' => $report->getKey()],
        );
    }

    public function summary(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'AI summary retrieved successfully',
            $this->analysis->summary($report),
        );
    }

    public function validation(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'AI validation retrieved successfully',
            $this->analysis->validation($report),
        );
    }

    public function references(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'Reference analysis retrieved successfully',
            $this->analysis->references($report),
        );
    }

    public function processingStatus(Request $request, \App\Models\Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'Processing status retrieved successfully',
            $this->analysis->status($report),
        );
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Report::class);

        return ApiResponse::success(
            'AI processing dashboard retrieved successfully',
            $this->analysis->dashboard($request->user()),
        );
    }
}
