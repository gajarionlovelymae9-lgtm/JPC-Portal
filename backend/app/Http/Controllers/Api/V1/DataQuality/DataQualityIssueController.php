<?php

namespace App\Http\Controllers\Api\V1\DataQuality;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataQuality\DismissDataQualityIssueRequest;
use App\Http\Requests\DataQuality\ResolveDataQualityIssueRequest;
use App\Http\Requests\DataQuality\ReviewDataQualityIssueRequest;
use App\Http\Resources\DataQuality\DataQualityIssueResource;
use App\Models\DataQualityIssue;
use App\Services\DataQuality\DataQualityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataQualityIssueController extends Controller
{
    public function __construct(
        private readonly DataQualityService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DataQualityIssue::class);

        $perPage = (int)$request->input('per_page', 15);
        $issues = $this->service->getIssues($request->user(), $request->all(), $perPage);

        return ApiResponse::collection(
            'Data quality issues retrieved successfully',
            DataQualityIssueResource::collection($issues)
        );
    }

    public function show(Request $request, DataQualityIssue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        $issue->load(['department', 'employee', 'reviewer', 'resolver', 'histories.performer']);

        return ApiResponse::success(
            'Data quality issue details retrieved successfully',
            new DataQualityIssueResource($issue)
        );
    }

    public function review(ReviewDataQualityIssueRequest $request, DataQualityIssue $issue): JsonResponse
    {
        $this->authorize('review', $issue);

        $updated = $this->service->reviewIssue($issue, $request->user(), $request->input('notes'));

        return ApiResponse::success(
            'Data quality issue marked under review',
            new DataQualityIssueResource($updated->load(['reviewer', 'histories.performer']))
        );
    }

    public function confirm(ReviewDataQualityIssueRequest $request, DataQualityIssue $issue): JsonResponse
    {
        $this->authorize('review', $issue);

        $updated = $this->service->confirmIssue($issue, $request->user(), $request->input('notes'));

        return ApiResponse::success(
            'Data quality issue confirmed as valid defect',
            new DataQualityIssueResource($updated->load(['reviewer', 'histories.performer']))
        );
    }

    public function dismiss(DismissDataQualityIssueRequest $request, DataQualityIssue $issue): JsonResponse
    {
        $this->authorize('review', $issue);

        $updated = $this->service->dismissIssue($issue, $request->user(), $request->input('reason'));

        return ApiResponse::success(
            'Data quality issue dismissed as false positive',
            new DataQualityIssueResource($updated->load(['reviewer', 'histories.performer']))
        );
    }

    public function resolve(ResolveDataQualityIssueRequest $request, DataQualityIssue $issue): JsonResponse
    {
        $this->authorize('resolve', $issue);

        $updated = $this->service->resolveIssue($issue, $request->user(), $request->input('notes'));

        return ApiResponse::success(
            'Data quality issue resolved successfully',
            new DataQualityIssueResource($updated->load(['resolver', 'histories.performer']))
        );
    }
}
