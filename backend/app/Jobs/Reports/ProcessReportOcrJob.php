<?php

namespace App\Jobs\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportFile;
use App\Services\AiReports\Ocr\OcrService;
use App\Services\ActivityLogs\ActivityLogService;
use App\Notifications\Reports\ReportOcrFailedNotification;
use App\Notifications\Reports\ReportOcrCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued OCR step for a single report file (Phase 3.3-K).
 *
 * Processed on the configured reports queue so uploads return immediately.
 * Successful runs transition the report to READY_FOR_AI_ANALYSIS; failures set
 * OCR_FAILED (retryable) and the employee is notified with the error — the
 * pipeline never silently continues with empty text and never fabricates.
 */
class ProcessReportOcrJob implements ShouldBeUnique
{
    /**
     * @param  \App\Models\ReportFile  $reportFile
     */
    public function __construct(public ReportFile $reportFile)
    {
        $this->onQueue((string) config('reports.queues.ocr', 'default'));
        $this->tries = (int) config('reports.jobs.ocr.tries', 3);
        $this->backoff = (array) config('reports.jobs.ocr.backoff', [30, 120, 300]);
        $this->timeout = (int) config('reports.jobs.ocr.timeout', 300);
    }

    public function uniqueId(): string
    {
        return 'report-ocr:'.$this->reportFile->id;
    }

    public function handle(OcrService $ocr, ActivityLogService $logs, ReportNotificationService $notify): void
    {
        $report = $this->reportFile->report;

        $this->reportFile->forceFill(['status' => 'processing'])->save();

        try {
            $processing = $ocr->process($this->reportFile);

            $report->forceFill([
                'status' => ReportStatus::ReadyForAiAnalysis->value,
                'ocr_confidence' => $processing->confidence,
                'ocr_completed_at' => now(),
            ])->save();

            $logs->log(
                $this->reportFile->uploadedBy,
                'report.ocr.completed',
                $report,
                'OCR completed for report file "'.$this->reportFile->original_filename.'".',
                ['report_file_id' => $this->reportFile->id, 'report_id' => $report->id],
            );

            $notify->ocrCompleted($report, $processing);
        } catch (Throwable $e) {
            $report->forceFill(['status' => ReportStatus::OcrFailed->value])->save();

            $logs->log(
                $this->reportFile->uploadedBy,
                'report.ocr.failed',
                $report,
                'OCR failed for report file "'.$this->reportFile->original_filename.'".',
                ['report_file_id' => $this->reportFile->id, 'error' => substr((string) $e->getMessage(), 0, 300)],
            );

            $notify->ocrFailed($report, $e);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->reportFile->report->forceFill(['status' => ReportStatus::OcrFailed->value])->save();
    }
}