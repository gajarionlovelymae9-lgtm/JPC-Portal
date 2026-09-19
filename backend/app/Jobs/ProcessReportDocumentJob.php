<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\AiReports\ReportAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Queued processing job for the AI/OCR pipeline (Phase 3.3-Q).
 *
 * Dispatches the report through the existing domain services:
 *   OCR → normalization → classification → summarization →
 *   requirement matching → reference analysis → consistency →
 *   confidence → finalize.
 *
 * Idempotent: a unique processing identifier prevents duplicate
 * final analyses when a retry fires.
 *
 * Human reviewer makes the final decision — this job only produces
 * analysis, never an administrative decision.
 */
class ProcessReportDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Report $report,
        public readonly string $reason,
        public readonly int $tries = 3,
        public readonly int $backoff = 5,
        public readonly int $timeout = 600,
    ) {}

    public function handle(ReportAnalysisService $analysis): void
    {
        $analysis->queue($this->report, $this->reason);
    }
}
