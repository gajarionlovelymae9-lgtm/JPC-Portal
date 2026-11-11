<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Ai\GenerateAiInsightJob;
use App\Jobs\Ai\ProcessAiChatJob;
use App\Jobs\ProcessReportDocumentJob;
use App\Jobs\Reports\AnalyzeReportJob;
use App\Models\Report;
use App\Services\AiReports\ReportAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 18 — Queue contract documentation tests.
 *
 * Phase 3/7 finalised several queued pipeline job classes, but only
 * `GenerateReportExportJob` is wired to a dispatch site. The other jobs are
 * either inert or reference services that do not exist, and the private
 * `EmployeePortalService::dispatchAiPipeline()` guard references the wrong
 * namespace so nothing is ever dispatched. These tests lock the current
 * (documented) behaviour so a future phase sees the contract change
 * deliberately rather than silently.
 */
class QueueContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ai_stub_jobs_are_queues_but_handles_are_empty_contracts(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ProcessAiChatJob);
        $this->assertInstanceOf(ShouldQueue::class, new GenerateAiInsightJob);

        // No-op handles: they must never throw when invoked.
        (new ProcessAiChatJob)->handle();
        (new GenerateAiInsightJob)->handle();
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function analyze_report_job_handle_cannot_be_resolved(): void
    {
        // The handle() signature type-hints AiReportAnalysisService which does
        // not exist anywhere in the app — dispatching this job fails at
        // resolve time. Lock current behaviour: it is not wired anywhere.
        $report = Report::factory()->create();
        $job = new AnalyzeReportJob($report);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('analyze-report:'.$report->id, $job->uniqueId());

        $this->expectExceptionMessageMatches('/AiReportAnalysisService/');

        // Matches how the queue worker fires handle(): container resolution.
        app()->call([$job, 'handle']);
    }

    #[Test]
    public function process_report_document_job_handle_fails_because_queue_method_missing(): void
    {
        $report = Report::factory()->create();
        $job = new ProcessReportDocumentJob($report, 'test');

        $this->assertInstanceOf(ShouldQueue::class, $job);

        // ReportAnalysisService::queue() does not exist — the job would fatally
        // error if it were ever dispatched with the real container.
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/queue\\(\\)/');

        $job->handle(app(ReportAnalysisService::class));
    }
}
