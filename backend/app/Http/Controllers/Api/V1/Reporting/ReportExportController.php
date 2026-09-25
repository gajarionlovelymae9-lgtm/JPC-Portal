<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ExportReportRequest;
use App\Jobs\Reporting\GenerateReportExportJob;
use App\Models\ReportExport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Phase 9 — Report Export API (spec items 32-33, 53-55).
 *
 * Queue-based pipeline: POST creates a QUEUED record and dispatches
 * GenerateReportExportJob; GET status polls it; GET download streams the file
 * for the owner using the unpredictable private path. Every lookup is
 * ownership-scoped (admins/hr see all).
 */
class ReportExportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isManager = in_array($user->roleName(), ['admin', 'hr'], true);

        $exports = ReportExport::query()
            ->when(! $isManager, fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->limit(50)
            ->get();

        return ApiResponse::success('Report exports retrieved successfully', $exports->map(fn ($e) => $this->present($e))->all());
    }

    public function queue(ExportReportRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Normalise the wire format (csv/excel/pdf) to the writer format
        // (ReportFilterBag::FORMATS: csv/xlsx/pdf).
        $format = $validated['format'];
        if ($format === 'excel') {
            $format = 'xlsx';
        }

        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => $validated['report_type'],
            'format' => $format,
            'status' => \App\Enums\ReportExportStatus::Queued->value,
            'filters' => $validated['filters'] ?? collect($validated)->except(['report_type', 'format'])->toArray(),
            'requested_at' => now(),
        ]);

        GenerateReportExportJob::dispatch($export);

        return ApiResponse::success('Report export queued successfully', $this->present($export), 202);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $export = ReportExport::find($id);

        if ($export === null) {
            return ApiResponse::error('Report export not found', null, 404);
        }

        if (! $this->canAccess($user, $export)) {
            return ApiResponse::error('Unauthorized to view this export', null, 403);
        }

        return ApiResponse::success('Report export retrieved successfully', $this->present($export));
    }

    public function download(Request $request, int $id): JsonResponse|BinaryFileResponse
    {
        $user = $request->user();
        $export = ReportExport::find($id);

        if ($export === null) {
            return ApiResponse::error('Report export not found', null, 404);
        }

        if (! $this->canAccess($user, $export)) {
            return ApiResponse::error('Unauthorized to download this export', null, 403);
        }

        if (! $export->isReadyForDownload()) {
            return ApiResponse::error('Export is not ready for download', null, 409);
        }

        if ($export->hasExpired() || ! $export->fileExists()) {
            $export->forceFill(['status' => \App\Enums\ReportExportStatus::Expired->value])->save();

            return ApiResponse::error('Export has expired', null, 410);
        }

        $path = Storage::disk(ReportExport::DISK)->path($export->file_path);

        return response()->download($path, $export->file_name ?? 'report_export.'.$export->format);
    }

    /**
     * Ownership check: owners always; admins/hr may act on any export.
     */
    private function canAccess(\App\Models\User $user, ReportExport $export): bool
    {
        return (int) $export->user_id === (int) $user->id
            || in_array($user->roleName(), ['admin', 'hr'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ReportExport $export): array
    {
        return [
            'id' => $export->id,
            'report_type' => $export->report_type,
            'format' => $export->format,
            'status' => $export->status,
            'status_label' => $export->status()->label(),
            'filters' => $export->filters,
            'file_name' => $export->file_name,
            'file_size' => $export->file_size,
            'row_count' => $export->row_count,
            'error_message' => $export->error_message,
            'error_line' => $export->error_line,
            'requested_at' => $export->requested_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
        ];
    }
}