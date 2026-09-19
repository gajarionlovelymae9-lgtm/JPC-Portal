<?php

namespace App\Jobs\Reports;

use App\Enums\ReportStatus;
use App\Models\ReportFile;
use App\Models\ReportProcessing;
use App\Services\AiReports\Ocr\OcrService;
use App\Services\AiReports\ReportNotificationService;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued OCR step for a single uploaded report file (Phase 3.3-K).
 *
 * Follows the spec lifecycle: upload → OCR → AI analysis → ready for review.
 * A failed attempt is recorded (reuse-friendly: a fresh attempt is created on
 * retry) and the report is moved to OCR_FAILED with a notification so the
 * employee can re-upload without losing the previous version.
 */
class ProcessReportFileOcr implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ReportFile $reportFile)
    {
        $this->onQueue(config('ai_reports.queue', 'default'));
        $this->tries = config('ai_reports.jobs.ocr.tries', 3);
        $this->backoff = config('ai_reports.jobs.ocr.backoff', [30, 120]);
        $this->timeout = config('ai_reports.jobs.ocr.timeout', 300);
    }

    /**
     * Prevent concurrent OCR of the same file (unique for one hour).
     */
    public int $uniqueFor = 3600;

    /**
     * A single job identity per report file.
     */
    public function uniqueId(): string
    {
        return 'process-report-ocr:'.$this->reportFile->id;
    }

    public function handle(OcrService $ocr, ActivityLogService $logs, ReportNotificationService $notifications): void
    {
        $report = $this->reportFile->report;

        $report->forceFill([
            'status' => ReportStatus::OcrProcessing->value,
            'processing_started_at' => now(),
        ])->save();

        $activity = $logs->log(
            $this->reportFile->uploader,
            'report.ocr.started',
            $report,
            'OCR started for report file '.$this->reportFile->original_filename.'.',
            ['report_file_id' => $this->reportFile->id, 'provider' => $ocr->providerName()],
        );

        try {
            $processing = $ocr->process($this->reportFile);

            if ($processing->status !== 'completed') {
                throw new \App\Services\AiReports\Ocr\Exceptions\OcrProcessingException(
                    'OCR did not complete: '.($processing->status ?? 'unknown'),
                );
            }

            $report->forceFill([
                'status' => ReportStatus::OcrCompleted->value,
                'ocr_confidence' => $processing->confidence_score,
                'ocr_completed_at' => now(),
            ])->save();

            $logs->log(
                $this->reportFile->uploader,
                'report.ocr.completed',
                $report,
                'OCR completed for '.$this->reportFile->original_filename.' (confidence '.
                    number_format((float) $processing->confidence_score, 4).').',
                ['report_file_id' => $this->reportFile->id, 'confidence' => $processing->confidence_score],
            );
        } catch (Throwable $e) {
            $report->forceFill(['status' => ReportStatus::OcrFailed->value])->save();

            $logs->log(
                $this->reportFile->uploader,
                'report.ocr.failed',
                $report,
                'OCR failed for '.$this->reportFile->original_filename.'.',
                ['report_file_id' => $this->reportFile->id, 'error' => $e->getMessage()],
            );

            $notifications->notifyOcrFailed($this->reportFile, $e);

            $this->fail($eowo);

            return;
        }
    }

    public function failed(Throwable $e): void
    {
        $report = $this->reportFile->report;

        $report->forceFill(['status' => ReportStatus::OcrFailed->value])->save();
    }
}