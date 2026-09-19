<?php

namespace App\Jobs\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Notifications\Reports\ReportAnalysisFailedNotification;
use App\Services\ActivityLogs\ActivityLogService;
use App\Services\AiReports\ReportAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * AI analysis over the extracted report text (Phase 3.3-K).
 *
 * Produces the summary, requirement/reference/completeness/consistency
 * validation and similarity results for the latest version, then moves the
 * report to READY_FOR_REVIEW. The AI result is advisory only — a failure
 * moves the report to AI_ANALYSIS_FAILED for a human to re-run; the AI never
 * approves or rejects a report.
 */
class AnalyzeReport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /** @var int One hour of uniqueness prevents duplicate analyses. */
    public int $uniqueFor = 3600;

    public int $tries;

    /** @var array<int, int> */
    public array $backoff;

    public int $timeout;

    public function __construct(public readonly Report $report)
    {
        $this->onQueue((string) config('ai_reports.queue', 'default'));
        $this->tries = (int) config('ai_reports.jobs.ai.tries', 3);
        $this->backoff = (array) config('ai_reports.jobs.ai.backoff', [120, 420]);
        $this->timeout = (int) config('ai_reports.jobs.ai.timeout', 480);
    }

    public function uniqueId(): string
    {
        return 'report-analyze:'.$this->report->id;
    }

    public function handle(ReportAnalysisService $analysis, ActivityLogService $activity): void
    {
        $analysis->analyze($this->report);

        $this->report->refresh();

        $activity->log(
            $this->report->uploader,
            'report.ai.processed',
            $this->report,
            'AI analysis completed for report "'.$this->report->title.'".',
            ['report_id' => $this->report->id, 'version_id' => $this->report->latest_version_id],
        );
    }

    public function failed(?Throwable $e): void
    {
        $report = $this->report->fresh();

        if (! $report) {
            return;
        }

        $report->forceFill(['status' => ReportStatus::AiAnalysisFailed->value])->save();

        $report->refresh();

        $report->task?->reviewers()?->each(
            fn ($user) => $user?->notify(new ReportAnalysisFailedNotification($report)),
        );
    }
}
