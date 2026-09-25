<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportReviewComment;
use App\Services\Reports\ReportReviewService;
use App\Services\Reports\ReportVersionService;
use App\Services\Reports\ReportWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportWorkflowController extends Controller
{
    public function __construct(
        protected ReportWorkflowService $workflowService,
        protected ReportReviewService $reviewService,
        protected ReportVersionService $versionService,
    ) {}

    /**
     * POST /api/v1/reports
     */
    public function storeDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => 'required|integer|exists:tasks,id',
            'title' => 'required|string|max:500',
            'content' => 'nullable|string',
            'references' => 'nullable|string',
            'remarks' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,docx,txt,png,jpg,jpeg|max:20480',
        ]);

        $file = $request->file('file');
        $report = $this->workflowService->createDraft($request->user(), $validated, $file);

        return ApiResponse::created('Report draft created successfully.', $report);
    }

    /**
     * PUT /api/v1/reports/{report}
     */
    public function updateDraft(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'references' => 'nullable|string',
            'remarks' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,docx,txt,png,jpg,jpeg|max:20480',
        ]);

        $file = $request->file('file');
        $updated = $this->workflowService->updateDraft($request->user(), $report, $validated, $file);

        return ApiResponse::success('Report draft updated successfully.', $updated);
    }

    /**
     * POST /api/v1/reports/{report}/submit
     */
    public function submit(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
            'file' => 'nullable|file|mimes:pdf,docx,txt,png,jpg,jpeg|max:20480',
        ]);

        $file = $request->file('file');
        $submitted = $this->workflowService->submit($request->user(), $report, $validated['notes'] ?? null, $file);

        return ApiResponse::success('Report submitted successfully.', $submitted);
    }

    /**
     * POST /api/v1/reports/{report}/request-revision
     */
    public function requestRevision(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
            'comments' => 'nullable|array',
            'comments.*.section_key' => 'nullable|string',
            'comments.*.comment' => 'required|string|max:2000',
        ]);

        $updated = $this->workflowService->requestRevision(
            $request->user(),
            $report,
            $validated['reason'],
            $validated['comments'] ?? null
        );

        return ApiResponse::success('Revision request sent to employee.', $updated);
    }

    /**
     * POST /api/v1/reports/{report}/revision
     */
    public function submitRevision(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'references' => 'nullable|string',
            'remarks' => 'nullable|string',
            'notes' => 'nullable|string|max:2000',
            'change_summary' => 'required|string|max:2000',
            'file' => 'nullable|file|mimes:pdf,docx,txt,png,jpg,jpeg|max:20480',
        ]);

        $file = $request->file('file');
        $updated = $this->workflowService->submitRevision($request->user(), $report, $validated, $file);

        return ApiResponse::success('Report revision submitted successfully.', $updated);
    }

    /**
     * POST /api/v1/reports/{report}/approve
     */
    public function approve(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'comments' => 'nullable|string|max:2000',
        ]);

        $approved = $this->workflowService->approve($request->user(), $report, $validated['comments'] ?? null);

        return ApiResponse::success('Report approved successfully.', $approved);
    }

    /**
     * POST /api/v1/reports/{report}/reject
     */
    public function reject(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $rejected = $this->workflowService->reject($request->user(), $report, $validated['reason']);

        return ApiResponse::success('Report rejected successfully.', $rejected);
    }

    /**
     * POST /api/v1/reports/{report}/cancel
     */
    public function cancel(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $cancelled = $this->workflowService->cancel($request->user(), $report, $validated['reason'] ?? null);

        return ApiResponse::success('Report cancelled successfully.', $cancelled);
    }

    /**
     * POST /api/v1/reports/{report}/archive
     */
    public function archive(Request $request, Report $report): JsonResponse
    {
        $archived = $this->workflowService->archive($request->user(), $report);

        return ApiResponse::success('Report archived successfully.', $archived);
    }

    /**
     * GET /api/v1/reports/{report}/timeline
     */
    public function timeline(Request $request, Report $report): JsonResponse
    {
        $timeline = $this->workflowService->getTimeline($report);

        return ApiResponse::success('Report timeline retrieved successfully.', $timeline);
    }

    /**
     * GET /api/v1/reports/{report}/versions
     */
    public function versions(Request $request, Report $report): JsonResponse
    {
        $versions = $this->versionService->getVersions($report);

        return ApiResponse::success('Report versions retrieved successfully.', $versions);
    }

    /**
     * GET /api/v1/reports/{report}/versions/{versionNumber}
     */
    public function versionDetail(Request $request, Report $report, int $versionNumber): JsonResponse
    {
        $version = $this->versionService->getVersion($report, $versionNumber);

        return ApiResponse::success("Report version v{$versionNumber} retrieved successfully.", $version);
    }

    /**
     * GET /api/v1/reports/{report}/versions-compare
     */
    public function compareVersions(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|integer|min:1',
            'to' => 'required|integer|min:1',
        ]);

        $diff = $this->versionService->compareVersions($report, (int) $validated['from'], (int) $validated['to']);

        return ApiResponse::success('Version comparison retrieved successfully.', $diff);
    }

    /**
     * GET /api/v1/reports/{report}/comments
     */
    public function comments(Request $request, Report $report): JsonResponse
    {
        $versionNumber = $request->query('version_number') ? (int) $request->query('version_number') : null;
        $comments = $this->reviewService->getComments($report, $versionNumber);

        return ApiResponse::success('Review comments retrieved successfully.', $comments);
    }

    /**
     * POST /api/v1/reports/{report}/comments
     */
    public function storeComment(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'section_key' => 'nullable|string|max:100',
            'comment' => 'required|string|max:2000',
            'version_number' => 'nullable|integer',
        ]);

        $comment = $this->reviewService->addComment($request->user(), $report, $validated);

        return ApiResponse::created('Review comment added successfully.', $comment);
    }

    /**
     * POST /api/v1/reports/{report}/comments/{comment}/resolve
     */
    public function resolveComment(Request $request, Report $report, ReportReviewComment $comment): JsonResponse
    {
        $resolved = $this->reviewService->resolveComment($request->user(), $report, $comment);

        return ApiResponse::success('Review comment resolved successfully.', $resolved);
    }
}
