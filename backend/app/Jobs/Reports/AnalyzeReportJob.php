<?php

namespace App\Jobs\Reports;

use App\Enums\ReportStatus;
use App\Enums\ReportVersionStatus;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Services\AiReports\Ocr\OcrService;
use App\Services\ActivityLogs\ActivityLogService;
use App\Services\AiReports\ReportNotificationService;
use App\Services\AiReports\ReportTextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued AI report analysis (Phase 3.3-K).
 *
 * Runs the complete advisory pipeline for the latest version of a report:
 * text extraction, AI summarization, requirement/reference matching, anchor
 * checking, consistency checking and similarity comparison. Output is stored
 * and the report moves to READY_FOR_REVIEW. A human (admin / department head /
 * HR) always makes the final decision — this job never approves or rejects.
 */
final class AnalyzeReportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 480;

    public int $uniqueFor = 3600;

    public function __construct(public readonly Report $report)
    {
        $this->onQueue((string) config('ai_reports.queue', 'default'));
        $this->tries = (int) config('ai_reports.jobs.analyze.tries', 3);
        $this->timeout = (int) config('ai_reports.jobs.analyze.timeout', 480);
    }

    public function uniqueId(): string
    {
        return 'analyze-report:'.$this->report->id;
    }

    public function handle(AiReportAnalysisService $analyzer, ActivityLogService $logs, ReportNotificationService $notify): void
    {
        $this->report->forceFill(['status' => ReportStatus::AiProcessing->value])->save();

        try {
            $result = $analyzer->analyze($this->report);

            $this->report->forceFill([
                'status' => ReportStatus::ReadyForReview->value,
                'ai_validated_at' => now(),
            ])->save();

            $logs->log(
                $this->report->uploader,
                'report.ai.completed',
                $this->report,
                'AI analysis completed for report "'.$this->report->title.'".',
                ['report_id' => $this->report->id, 'ai_score' => $result['overall'] ?? null],
            );

            $notify->readyForReview($this->report);
        } catch (Throwable $e) {
            $this->report->forceFill(['status' => ReportStatus::AiFailed->value])->save();

            $logs->log(
                $this->report->uploader,
                'report.ai.failed',
                $this->report,
                'AI analysis failed for report "'.$this->report->title.'".',
                ['report_id' => $this->report->id, 'error' => substr($e->getMessage(), 0, 500)],
            );

            $notify->analysisFailed($this->report, $e);

            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        if (! $this->report->exists) {
            return;
        }

        $this->report->forceFill(['status' => ReportStatus::AiFailed->value])->save();
    }
}