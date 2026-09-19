<?php

namespace App\Jobs\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportFile;
use App\Services\AiReports\Ocr\OcrService;
use App\Services\ActivityLogs\ActivityLogService;
use App\Services\Notifications\Reports\ReportNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Phase 3.3-K — queued OCR pass for a single report file.
 *
 * Only the file is persisted here; the extracted text, provider confidence and
 * result JSON route through ReportFile->ocrProcessings. The job is idempotent:
 * a completed OCR run for the same file is reused instead of re-requested, so
 * retries never double-charge the providerworkload.
 */
class ProcessReportOcr implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(public readonly ReportFile $reportFile)
    {
        $this->onQueue((string) config('ai_reports.queue', 'default'));
        $this->tries = (int) config('ai_reports.jobs.ocr.tries', 3);
        $this->backoff = (array) config('ai_reports.jobs.ocr.backoff', [60, 240]);
        $this->timeout = (int) config('ai_reports.jobs.ocr.timeout', 300);
    }

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'ocr:'.$this->reportFile->id;
    }

    public function handle(OcrService $ocr, ActivityLogService $logs, ReportNotificationService $notifications): void
    {
        $report = $this->reportFile->report()->firstOrFail();

        $report->forceFill([
            'status' => ReportStatus::OcrProcessing->value,
            'processing_started_at' => now(),
        ])->save();

        try {
            $processing = $ocr->process($this->reportFile);

            if ($processing->status !== 'completed') {
                throw new \RuntimeException('OCR run ended with status "'.$processing->status.'".');
            }

            $logs->log(
                $this->reportFile->uploader,
                'report.ocr.completed',
                $report,
                'OCR completed for report file "'.$this->reportFile->original_filename.'" (confidence '.
                    number_format((float) $processing->confidence_score, 4).').',
                ['report_id' => $report->id, 'report_file_id' => $this->reportFile->id, 'confidence' => (float) $processing->confidence_score],
            );
        } catch (Throwable $e) {
            $report->forceFill(['status' => ReportStatus::OcrFailed->value])->save();

            $logs->log(
                $this->reportFile->uploader,
                'report.ocr.failed',
                $report,
                'OCR failed for report file "'.$this->reportFile->original_filename.'": '.$e->getMessage(),
                ['report_id' => $report->id, 'report_file_id' => $this->reportFile->id],
            );

            $notifications->notifyOcrFailed($this->reportFile);

            $this->release(max(60, (int) ($this->backoff[0] ?? 60)));

            return;
        }
    }

    public function failed(Throwable $e): void
    {
        $report = $this->reportFile->report()->first();

        if (! $report) {
            return;
        }

        $report->forceFill(['status' => ReportStatus::OcrFailed->value])->save();
    }
}