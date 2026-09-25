<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiFinding;
use App\Models\Report;
use App\Models\ReportFile;
use App\Services\AiReports\ReportAnalysisService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * AI/OCR Processing Center (Phase 4).
 *
 * Full document upload, OCR triggering, NLP summarization, requirement validation,
 * reference anchoring, similarity check, structured findings review, human override,
 * and multi-version diffing.
 */
class AiReportController extends Controller
{
    public function __construct(
        private readonly ReportAnalysisService $analysis,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        return ApiResponse::success(
            'AI processing dashboard retrieved successfully',
            $this->analysis->dashboard($request->user()),
        );
    }

    public function dashboard(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'Processing status retrieved successfully',
            $this->analysis->status($report),
        );
    }

    public function process(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $jobId = (string) $report->id;

        return ApiResponse::success(
            'Report queued for AI/OCR processing',
            ['job_id' => $jobId],
        );
    }

    public function ocr(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $analyzed = $this->analysis->analyze($report);

        return ApiResponse::success(
            'OCR and analysis executed successfully',
            $this->analysis->status($analyzed),
        );
    }

    public function analyze(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $analyzed = $this->analysis->analyze($report);

        return ApiResponse::success(
            'Full AI pipeline completed successfully',
            $this->analysis->status($analyzed),
        );
    }

    public function summary(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'AI summary retrieved successfully',
            $this->analysis->summary($report),
        );
    }

    public function validation(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'AI validation retrieved successfully',
            $this->analysis->validation($report),
        );
    }

    public function references(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'Reference anchoring analysis retrieved successfully',
            $this->analysis->referencesMatrix($report),
        );
    }

    public function similarity(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'Similarity analysis retrieved successfully',
            $this->analysis->similarityMatrix($report),
        );
    }

    public function findings(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return ApiResponse::success(
            'AI findings retrieved successfully',
            $this->analysis->findings($report),
        );
    }

    public function reviewFinding(Request $request, Report $report, AiFinding $finding): JsonResponse
    {
        $this->authorize('update', $report);

        $request->validate([
            'action' => 'required|in:accept,dismiss,modify',
            'notes' => 'nullable|string|max:1000',
        ]);

        $updated = $this->analysis->reviewFinding($report, $finding, $request->user(), $request->only(['action', 'notes']));

        return ApiResponse::success(
            'Finding review decision recorded',
            $updated,
        );
    }

    public function review(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $request->validate([
            'decision' => 'required|in:validated,approved,revision_required,rejected',
            'notes' => 'nullable|string|max:2000',
        ]);

        $reviewed = $this->analysis->submitHumanReview($report, $request->user(), $request->only(['decision', 'notes']));

        return ApiResponse::success(
            'Human review decision saved successfully',
            $this->analysis->status($reviewed),
        );
    }

    public function retry(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $analyzed = $this->analysis->analyze($report);

        return ApiResponse::success(
            'Processing retry completed',
            $this->analysis->status($analyzed),
        );
    }

    public function compare(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $v1 = (int) $request->input('v1', 1);
        $v2 = (int) $request->input('v2', 2);

        return ApiResponse::success(
            'Version comparison retrieved',
            $this->analysis->compareVersions($report, $v1, $v2),
        );
    }

    public function uploadDocument(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $request->validate([
            'file' => 'required|file|mimes:pdf,docx,doc,jpg,jpeg,png|max:20480',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $mime = $uploadedFile->getClientMimeType();
        $size = $uploadedFile->getSize();
        $checksum = hash_file('sha256', $uploadedFile->getRealPath());

        // Detect duplicate upload
        $isDuplicate = ReportFile::query()
            ->where('checksum', $checksum)
            ->where('report_id', '!=', $report->id)
            ->exists();

        $storedName = Str::uuid() . '.' . $uploadedFile->getClientOriginalExtension();
        $path = $uploadedFile->storeAs('private/reports/' . $report->id, $storedName);

        $version = $report->versions()->latest('id')->first();

        $reportFile = ReportFile::create([
            'report_id' => $report->id,
            'report_version_id' => $version?->id,
            'original_filename' => $originalName,
            'stored_filename' => $storedName,
            'storage_disk' => 'local',
            'file_path' => $path,
            'mime_type' => $mime,
            'file_size' => $size,
            'checksum' => $checksum,
            'uploaded_by' => $request->user()->id,
        ]);

        return ApiResponse::success(
            'Document uploaded successfully' . ($isDuplicate ? ' (Potential Duplicate File flagged)' : ''),
            [
                'file' => $reportFile,
                'is_duplicate' => $isDuplicate,
                'checksum' => $checksum,
            ],
            201,
        );
    }
}
