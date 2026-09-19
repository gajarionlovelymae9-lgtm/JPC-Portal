<?php

namespace App\Services\AiReports;

use App\Models\User;
use App\Models\AiAnalysis;
use App\Models\AiSummary;
use App\Models\AiValidationItem;
use App\Models\AiValidationResult;
use App\Models\Report;
use App\Models\ReportReference;
use App\Models\ReportVersion;
use App\Services\AiReports\Ai\AiReportPromptBuilder;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\Ai\JsonSchemaValidator;
use App\Services\AiReports\Ai\PromptInjectionGuard;
use App\Services\ActivityLogs\ActivityLogService;
use App\Services\Notifications\NotificationService;

/**
 * Orchestrates the AI phase of a report submission (Phase 3.3-K).
 *
 * For the latest report version it produces one AiAnalysis execution per
 * analysis type — "summarization" then "validation" — persisted as immutable
 * history rows, plus similarity comparisons against other employee reports on
 * the same task. A successful run moves the report to ready_for_review where
 * only the human reviewer can approve or reject it.
 */
final class ReportAnalysisService
{
    public function __construct(
        private AiProvider $provider,
        private JsonSchemaValidator $validator,
        private AiReportPromptBuilder $prompts,
        private ReportTextExtractor $extractor,
        private ReferenceAnalyzer $references,
        private SimilarityService $similarity,
    ) {}

    public function analyze(Report $report): Report
    {
        $extraction = $this->extractor->extract($report);
        $version = $report->versions()->latest('id')->firstOrFail();

        $hash = hash('sha256', $extraction['text']);

        $executed = false;
        $executed = $this->analyzeSummary($report, $version, $extraction, $hash) || $executed;
        $executed = $this->analyzeValidation($report, $version, $extraction, $hash) || $executed;

        if ($executed) {
            $this->runSimilarity($report);
        }

        $report->forceFill(['status' => \App\Enums\ReportStatus::ReadyForReview->value])->save();

        return $report->fresh();
    }

    /**
     * Run a single analysis type; returns true when a provider call happened
     * (as opposed to being skipped by the completed-reuse guard).
     */
    private function analyzeSummary(Report $report, ReportVersion $version, array $extraction, string $hash): bool
    {
        $analysis = $this->newAnalysis($report, $version, 'summarization', $hash);

        if ($analysis === null) {
            return false;
        }

        try {
            $level = config('ai_reports.ai.summary_default_level', 'medium');
            if (! in_array($level, config('ai_reports.ai.summary_levels', ['medium']), true)) {
                $level = 'medium';
            }

            $completion = $this->provider->complete(
                $this->prompts->systemRules(),
                $this->prompts->summarizePrompt($extraction['text'], $this->taskContext($report), $level),
                $this->request('summarization'),
            );

            $payload = $this->validator->summary($this->validator->decode($completion->text));

            $summary = new AiSummary;
            $summary->forceFill([
                'report_id' => $report->id,
                'ai_analysis_id' => $analysis->id,
                'summary' => $payload['overview'],
                'key_activities' => $payload['key_activities'],
                'key_accomplishments' => $payload['key_accomplishments'],
                'key_findings' => $payload['results'],
                'results' => $payload['results'],
                'issues' => $payload['issues'],
                'recommendations' => $payload['recommendations'],
                'action_items' => $payload['action_items'],
                'generated_at' => now(),
            ])->save();

            $analysis->forceFill(['status' => 'completed', 'completed_at' => now(), 'error_message' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $this->failAnalysis($analysis, $e);

            throw $e;
        }
    }

    private function analyzeValidation(Report $report, ReportVersion $version, array $extraction, string $hash): bool
    {
        $analysis = $this->newAnalysis($report, $version, 'validation', $hash);

        if ($analysis === null) {
            return false;
        }

        try {
            $anchor = $this->references->analyze($extraction['text'], $report);
            $this->persistScannedReferences($report, $anchor['urls']);

            $completion = $this->provider->complete(
                $this->prompts->systemRules(),
                $this->prompts->validatePrompt(
                    $extraction['text'],
                    $this->taskContext($report),
                    $extraction['confidence'],
                    $anchor['anchor_score'],
                ),
                $this->request('validation'),
            );

            $payload = $this->validator->validation($this->validator->decode($completion->text));

            $validation = new AiValidationResult;
            $validation->forceFill([
                'report_id' => $report->id,
                'ai_analysis_id' => $analysis->id,
                'task_alignment_score' => $payload['scores']['task_alignment_score'],
                'reference_alignment_score' => $payload['scores']['reference_alignment_score'],
                'reference_anchor_score' => $payload['scores']['reference_anchor_score'],
                'completeness_score' => $payload['scores']['completeness_score'],
                'relevance_score' => $payload['scores']['relevance_score'],
                'evidence_score' => $payload['scores']['evidence_score'],
                'clarity_score' => $payload['scores']['clarity_score'],
                'overall_score' => $payload['scores']['overall_score'],
                'requirement_match' => $payload['requirement_match'],
                'confidence_score' => $payload['confidence'],
                'result' => $payload['result'],
                'explanation' => $this->withContainmentNote($payload['explanation'], $extraction['text']),
            ])->save();

            foreach ($payload['issues'] as $issue) {
                $item = new AiValidationItem;
                $item->forceFill([
                    'ai_validation_result_id' => $validation->id,
                    'category' => $issue['category'],
                    'severity' => $issue['severity'],
                    'finding' => $issue['finding'],
                    'evidence' => $issue['evidence'],
                    'recommendation' => $issue['recommendation'],
                ])->save();
            }

            $analysis->forceFill(['status' => 'completed', 'completed_at' => now(), 'error_message' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $this->failAnalysis($analysis, $e);

            throw $e;
        }
    }

    private function newAnalysis(Report $report, ReportVersion $version, string $type, string $hash): ?AiAnalysis
    {
        if (config('ai_reports.analysis.reuse_completed', true)) {
            $existing = $report->aiAnalyses()
                ->where('analysis_type', $type)
                ->where('report_version_id', $version->id)
                ->where('status', 'completed')
                ->where('input_hash', $hash)
                ->latest('id')
                ->first();

            if ($existing instanceof AiAnalysis) {
                return null;
            }
        }

        $analysis = new AiAnalysis;
        $analysis->forceFill([
            'report_id' => $report->id,
            'report_version_id' => $version->id,
            'analysis_type' => $type,
            'provider' => $this->provider->name(),
            'model' => (string) config('ai_reports.ai.model'),
            'analysis_version' => (string) config('ai_reports.ai.analysis_version'),
            'status' => 'processing',
            'input_hash' => $hash,
            'started_at' => now(),
        ])->save();

        return $analysis;
    }

    private function failAnalysis(AiAnalysis $analysis, \Throwable $e): void
    {
        $analysis->forceFill([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => substr($e->getMessage(), 0, 2000),
        ])->save();
    }

    /**
     * @param  list<string>  $urls
     */
    private function persistScannedReferences(Report $report, array $urls): void
    {
        foreach ($urls as $url) {
            $exists = $report->references()->where('reference_url', $url)->exists();

            if (! $exists) {
                $reference = new ReportReference;
                $reference->forceFill([
                    'report_id' => $report->id,
                    'reference_text' => $url,
                    'reference_url' => $url,
                ])->save();
            }
        }
    }

    private function runSimilarity(Report $report): void
    {
        $candidates = $report->task
            ? $report->task
                ->reports()
                ->where('reports.id', '!=', $report->id)
                ->whereNotIn('reports.status', ['rejected', 'draft', 'ocr_failed', 'ai_analysis_failed', 'failed'])
                ->limit(15)
                ->get()
            : collect();

        foreach ($candidates as $other) {
            $score = $this->similarity->compare($report, $other);
            $status = $this->similarity->status($score);

            $row = $report->aiSimilarityResults()->updateOrCreate(
                ['compared_report_id' => $other->id],
                ['similarity_score' => $score, 'status' => $status, 'explanation' => $this->similarityExplanation($score, $other)],
            );

            if ($all = $other->aiSimilarityResults()->where('compared_report_id', $report->id)->first()) {
                // keep the reciprocal comparison cached as well for symmetric lookup
                $all->forceFill(['similarity_score' => $score, 'status' => $status, 'explanation' => $this->similarityExplanation($score, $other)])->save();
            }
        }
    }

    private function similarityExplanation(float $score, Report $other): string
    {
        return 'Token-level similarity against report #'.$other->id.' (task #'.($other->task_id ?? '-').') is '.number_format($score * 100, 1).'%. Advisory only.';
    }

    private function request(string $type): AiRequest
    {
        return new AiRequest(
            model: (string) config('ai_reports.ai.model'),
            maxTokens: (int) config('ai_reports.ai.http.max_tokens', 2048),
            temperature: (float) config('ai_reports.ai.http.temperature', 0.1),
            expectJson: true,
            metadata: ['analysis_type' => $type],
        );
    }

    private function taskContext(Report $report): string
    {
        $task = $report->task;

        if (! $task) {
            return 'No task specification is attached to this report.';
        }

        $lines = [trim((string) $task->title)];

        if (filled((string) $task->description)) {
            $lines[] = trim((string) $task->description);
        }

        foreach ($task->references as $reference) {
            $lines[] = '- '.trim((string) ($reference->title ?? $reference->description ?? $reference->reference_url ?? ''));
        }

        return implode("\n", array_values(array_filter($lines)));
    }

    private function withContainmentNote(string $explanation, string $text): string
    {
        if ((new PromptInjectionGuard())->containsInstructionOverride($text)) {
            return trim($explanation.' '.PromptInjectionGuard::containmentLabel());
        }

        return $explanation;
    }

    /**
     * Processing dashboard counts for a user.
     */
    public function dashboard(User $user): array
    {
        $employeeId = $user->getKey();
        $total = Report::query()->where('employee_id', $employeeId)->count();
        $queued = Report::query()->where('employee_id', $employeeId)->where('status', 'queued')->count();
        $processing = Report::query()->where('employee_id', $employeeId)->where('status', 'ai_processing')->count();
        $ocrCompleted = Report::query()->where('employee_id', $employeeId)->where('status', 'ocr_completed')->count();
        $aiCompleted = Report::query()->where('employee_id', $employeeId)->where('status', 'ai_completed')->count();
        $failed = Report::query()->where('employee_id', $employeeId)->whereIn('status', ['failed', 'ocr_failed', 'ai_analysis_failed'])->count();
        $needsReview = Report::query()->where('employee_id', $employeeId)->where('status', 'ready_for_review')->count();

        return compact('total', 'queued', 'processing', 'ocrCompleted', 'aiCompleted', 'failed', 'needsReview');
    }

    public function queue(Report $report, string $reason = ''): string
    {
        return (string) $report->getKey();
    }

    /**
     * Processing status for a report.
     */
    public function status(Report $report): array
    {
        return [
            'status' => $report->status,
            'report_id' => $report->id,
            'ai_analyses' => $report->aiAnalyses()
                ->latest('created_at')
                ->get()
                ->map(fn (AiAnalysis $a) => [
                    'version' => $a->analysis_version,
                    'date' => $a->created_at->toIso8601String(),
                    'provider' => $a->provider,
                    'model' => $a->model,
                    'status' => $a->status,
                ]),
        ];
    }

    /**
     * AI summary for a report.
     */
    public function summary(Report $report): ?array
    {
        $summary = $report->aiSummaries()->latest('created_at')->first();

        return $summary ? [
            'executive_summary' => $summary->overview,
            'key_activities' => $summary->key_activities,
            'key_accomplishments' => $summary->key_accomplishments,
            'results' => $summary->results,
            'issues' => $summary->issues,
            'recommendations' => $summary->recommendations,
        ] : null;
    }

    /**
     * AI validation for a report.
     */
    public function validation(Report $report): ?array
    {
        $validation = $report->aiValidationResults()->latest('created_at')->first();

        return $validation ? [
            'requirement_match' => $validation->requirement_match,
            'completeness_score' => $validation->completeness_score,
            'reference_alignment_score' => $validation->reference_alignment_score,
            'consistency_score' => $validation->clarity_score,
            'overall_score' => $validation->overall_score,
            'issues' => $validation->items->map(fn (AiValidationItem $i) => [
                'category' => $i->category,
                'severity' => $i->severity,
                'finding' => $i->finding,
                'evidence' => $i->evidence,
            ]),
        ] : null;
    }

    /**
     * References extracted for a report.
     */
    public function references(Report $report): array
    {
        return $report->references->map(fn (ReportReference $r) => [
            'text' => $r->reference_text,
            'url' => $r->reference_url,
        ])->all();
    }
}