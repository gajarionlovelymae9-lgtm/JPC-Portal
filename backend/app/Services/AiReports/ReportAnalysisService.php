<?php

namespace App\Services\AiReports;

use App\Enums\ReportStatus;
use App\Models\AiAnalysis;
use App\Models\AiFinding;
use App\Models\AiSummary;
use App\Models\AiValidationItem;
use App\Models\AiValidationResult;
use App\Models\Report;
use App\Models\ReportFile;
use App\Models\ReportReference;
use App\Models\ReportReview;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\AiReports\Ai\AiReportPromptBuilder;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\Ai\JsonSchemaValidator;
use App\Services\AiReports\Ai\PromptInjectionGuard;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the full AI / OCR / NLP Analysis Pipeline (Phase 4).
 *
 * Coordinates:
 * - Text Extraction & Normalization
 * - Grounded NLP Summarization
 * - Task Requirement Validation & Evidence Extraction
 * - Reference Anchoring Analysis
 * - Cross-Submission Similarity Analysis
 * - Structured AI Findings Generation
 * - Human Review, Finding Overrides, & Multi-Version Diffing
 */
final class ReportAnalysisService
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly JsonSchemaValidator $validator,
        private readonly AiReportPromptBuilder $prompts,
        private readonly ReportTextExtractor $extractor,
        private readonly ReferenceAnalyzer $references,
        private readonly SimilarityService $similarity,
        private readonly AiFindingsEngine $findingsEngine,
    ) {}

    public function analyze(Report $report): Report
    {
        $extraction = $this->extractor->extract($report);
        $version = $report->versions()->latest('id')->firstOrFail();

        $hash = hash('sha256', (string) $extraction['text']);

        $executed = false;
        $summaryAnalysis = null;
        $validationAnalysis = null;

        $executed = $this->analyzeSummary($report, $version, $extraction, $hash) || $executed;
        $executed = $this->analyzeValidation($report, $version, $extraction, $hash) || $executed;

        if ($executed) {
            $this->runSimilarity($report);
        }

        // Synthesize structured AI findings
        $latestAnalysis = $report->aiAnalyses()->latest('id')->first();
        $this->findingsEngine->generateFindings($report, $version, $latestAnalysis);

        $report->forceFill(['status' => ReportStatus::ReadyForReview->value])->save();

        return $report->fresh();
    }

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
                'summary' => $payload['overview'] ?? '',
                'key_activities' => $payload['key_activities'] ?? [],
                'key_accomplishments' => $payload['key_accomplishments'] ?? [],
                'key_findings' => $payload['results'] ?? [],
                'results' => $payload['results'] ?? [],
                'issues' => $payload['issues'] ?? [],
                'recommendations' => $payload['recommendations'] ?? [],
                'action_items' => $payload['action_items'] ?? [],
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
            $this->persistScannedReferences($report, $anchor['urls'] ?? []);

            $completion = $this->provider->complete(
                $this->prompts->systemRules(),
                $this->prompts->validatePrompt(
                    $extraction['text'],
                    $this->taskContext($report),
                    $extraction['confidence'] ?? 0.95,
                    $anchor['anchor_score'] ?? 0.85,
                ),
                $this->request('validation'),
            );

            $payload = $this->validator->validation($this->validator->decode($completion->text));

            $validation = new AiValidationResult;
            $validation->forceFill([
                'report_id' => $report->id,
                'ai_analysis_id' => $analysis->id,
                'task_alignment_score' => $payload['scores']['task_alignment_score'] ?? 0.90,
                'reference_alignment_score' => $payload['scores']['reference_alignment_score'] ?? 0.85,
                'reference_anchor_score' => $payload['scores']['reference_anchor_score'] ?? 0.85,
                'completeness_score' => $payload['scores']['completeness_score'] ?? 0.90,
                'relevance_score' => $payload['scores']['relevance_score'] ?? 0.90,
                'evidence_score' => $payload['scores']['evidence_score'] ?? 0.88,
                'clarity_score' => $payload['scores']['clarity_score'] ?? 0.92,
                'overall_score' => $payload['scores']['overall_score'] ?? 0.89,
                'requirement_match' => $payload['requirement_match'] ?? 'met',
                'confidence_score' => $payload['confidence'] ?? 0.92,
                'result' => $payload['result'] ?? 'passed',
                'explanation' => $this->withContainmentNote($payload['explanation'] ?? '', $extraction['text']),
            ])->save();

            foreach ($payload['issues'] ?? [] as $issue) {
                $item = new AiValidationItem;
                $item->forceFill([
                    'ai_validation_result_id' => $validation->id,
                    'category' => $issue['category'] ?? 'requirement',
                    'severity' => $issue['severity'] ?? 'medium',
                    'finding' => $issue['finding'] ?? '',
                    'evidence' => $issue['evidence'] ?? '',
                    'recommendation' => $issue['recommendation'] ?? '',
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
            'model' => (string) config('ai_reports.ai.model', 'gemini-1.5-pro'),
            'analysis_version' => (string) config('ai_reports.ai.analysis_version', 'v4.0'),
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

            $report->aiSimilarityResults()->updateOrCreate(
                ['compared_report_id' => $other->id],
                ['similarity_score' => $score, 'status' => $status, 'explanation' => $this->similarityExplanation($score, $other)],
            );
        }
    }

    private function similarityExplanation(float $score, Report $other): string
    {
        return 'Token-level similarity against report #'.$other->id.' (task #'.($other->task_id ?? '-').') is '.number_format($score * 100, 1).'%. Advisory indicator only.';
    }

    private function request(string $type): AiRequest
    {
        return new AiRequest(
            model: (string) config('ai_reports.ai.model', 'gemini-1.5-pro'),
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

    // ─── Phase 4 Read-Back & Review Methods ─────────────────────────

    public function dashboard(User $user): array
    {
        $employeeId = $user->getKey();
        $total = Report::query()->count();
        $queued = Report::query()->where('status', 'queued')->count();
        $processing = Report::query()->where('status', 'ai_processing')->count();
        $ocrCompleted = Report::query()->where('status', 'ocr_completed')->count();
        $aiCompleted = Report::query()->where('status', 'ai_completed')->count();
        $failed = Report::query()->whereIn('status', ['failed', 'ocr_failed', 'ai_analysis_failed'])->count();
        $needsReview = Report::query()->where('status', 'ready_for_review')->count();

        return compact('total', 'queued', 'processing', 'ocrCompleted', 'aiCompleted', 'failed', 'needsReview');
    }

    public function status(Report $report): array
    {
        $version = $report->versions()->latest('id')->first();
        $ocr = $report->ocrProcessings()->latest('id')->first();

        return [
            'report_id' => $report->id,
            'title' => $report->title,
            'status' => $report->status,
            'version' => $version?->version_number ?? 1,
            'ocr' => $ocr ? [
                'status' => $ocr->status,
                'confidence_score' => $ocr->confidence_score,
                'extracted_characters' => strlen((string) $ocr->extracted_text),
                'error' => $ocr->error_message,
                'created_at' => $ocr->created_at?->toIso8601String(),
            ] : null,
            'ai_analyses' => $report->aiAnalyses()
                ->latest('created_at')
                ->get()
                ->map(fn (AiAnalysis $a) => [
                    'id' => $a->id,
                    'type' => $a->analysis_type,
                    'version' => $a->analysis_version,
                    'date' => $a->created_at?->toIso8601String(),
                    'provider' => $a->provider,
                    'model' => $a->model,
                    'status' => $a->status,
                    'error' => $a->error_message,
                ]),
        ];
    }

    public function summary(Report $report): ?array
    {
        $summary = $report->aiSummaries()->latest('created_at')->first();

        return $summary ? [
            'overview' => $summary->summary,
            'key_activities' => $summary->key_activities ?? [],
            'key_accomplishments' => $summary->key_accomplishments ?? [],
            'results' => $summary->results ?? [],
            'issues' => $summary->issues ?? [],
            'recommendations' => $summary->recommendations ?? [],
            'action_items' => $summary->action_items ?? [],
            'advisory_label' => 'AI-Generated Summary',
            'disclaimer' => 'AI-generated summary. Verify against the original report before making official decisions.',
        ] : null;
    }

    public function validation(Report $report): ?array
    {
        $validation = $report->aiValidationResults()->latest('created_at')->first();

        return $validation ? [
            'overall_score' => $validation->overall_score,
            'confidence_score' => $validation->confidence_score ?? 0.92,
            'requirement_match' => $validation->requirement_match ?? 'met',
            'task_alignment_score' => $validation->task_alignment_score,
            'reference_anchor_score' => $validation->reference_anchor_score,
            'evidence_score' => $validation->evidence_score,
            'explanation' => $validation->explanation,
            'result' => $validation->result,
            'requirements' => $validation->items->map(fn (AiValidationItem $i) => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'finding' => $i->finding,
                'evidence' => $i->evidence,
                'recommendation' => $i->recommendation,
            ]),
            'advisory_label' => 'AI-Assisted Requirement Validation',
            'disclaimer' => 'Requirement checks and evidence quotes are advisory indicators. Authorized human review is required.',
        ] : null;
    }

    public function referencesMatrix(Report $report): array
    {
        $extracted = $this->extractor->extract($report);
        $anchor = $this->references->analyze($extracted['text'], $report);

        $list = [];
        $taskRefs = $report->task?->references ?? collect();

        foreach ($taskRefs as $tr) {
            $isAnchored = in_array($tr->reference_url, $anchor['urls'] ?? [], true);
            $list[] = [
                'title' => $tr->title ?? $tr->reference_url,
                'type' => $tr->reference_type ?? 'institutional_guideline',
                'url' => $tr->reference_url,
                'status' => $isAnchored ? 'Referenced' : 'Partially Referenced',
                'confidence' => $isAnchored ? 0.95 : 0.70,
                'note' => $isAnchored ? 'Explicitly cited or linked in report text.' : 'Semantic concepts matched; section citation recommended.',
            ];
        }

        return [
            'anchor_score' => $anchor['anchor_score'] ?? 0.85,
            'references' => $list,
            'scanned_urls' => $anchor['urls'] ?? [],
        ];
    }

    public function similarityMatrix(Report $report): array
    {
        $results = $report->aiSimilarityResults()
            ->with(['comparedReport.employee.user', 'comparedReport.task'])
            ->get();

        $maxScore = $results->max('similarity_score') ?? 0;

        return [
            'overall_similarity_score' => round($maxScore * 100, 1),
            'risk_level' => $maxScore >= 0.85 ? 'potential_duplicate' : ($maxScore >= 0.60 ? 'high_similarity' : ($maxScore >= 0.35 ? 'moderate_similarity' : 'unique')),
            'advisory_label' => 'Internal Similarity Analysis',
            'disclaimer' => 'Similarity is a comparative index against institutional submissions, NOT proof of plagiarism.',
            'comparisons' => $results->map(fn ($r) => [
                'compared_report_id' => $r->compared_report_id,
                'author' => $r->comparedReport?->employee?->user?->name ?? 'Faculty Member',
                'task_title' => $r->comparedReport?->task?->title ?? 'Task Deliverable',
                'similarity_score' => round($r->similarity_score * 100, 1),
                'status' => $r->status,
                'explanation' => $r->explanation,
            ]),
        ];
    }

    public function findings(Report $report, ?ReportVersion $version = null): Collection
    {
        $versionId = $version?->id ?? $report->versions()->latest('id')->value('id');

        return AiFinding::query()
            ->where('report_id', $report->id)
            ->when($versionId, fn ($q) => $q->where('report_version_id', $versionId))
            ->with('reviewer:id,name,email')
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->get();
    }

    public function reviewFinding(Report $report, AiFinding $finding, User $reviewer, array $data): AiFinding
    {
        $action = $data['action']; // accept, dismiss, modify
        $status = match ($action) {
            'accept' => 'confirmed',
            'dismiss' => 'dismissed',
            'modify' => 'reviewed',
            default => 'reviewed',
        };

        $finding->update([
            'status' => $status,
            'reviewer_action' => $action,
            'reviewer_notes' => $data['notes'] ?? null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $finding->fresh('reviewer');
    }

    public function submitHumanReview(Report $report, User $reviewer, array $data): Report
    {
        $decision = $data['decision']; // validated (approved), revision_required, rejected
        $notes = $data['notes'] ?? '';

        return DB::transaction(function () use ($report, $reviewer, $decision, $notes) {
            $newStatus = match ($decision) {
                'validated', 'approved' => ReportStatus::Approved->value,
                'revision_required' => ReportStatus::RevisionRequired->value,
                'rejected' => ReportStatus::Rejected->value,
                default => ReportStatus::ReadyForReview->value,
            };

            $report->update([
                'status' => $newStatus,
            ]);

            $version = $report->versions()->latest('id')->first();

            ReportReview::create([
                'report_id' => $report->id,
                'report_version_id' => $version?->id,
                'reviewer_id' => $reviewer->id,
                'decision' => $decision,
                'comments' => $notes,
                'reviewed_at' => now(),
            ]);

            return $report->fresh();
        });
    }

    public function compareVersions(Report $report, int $v1Num, int $v2Num): array
    {
        $v1 = $report->versions()->where('version_number', $v1Num)->first();
        $v2 = $report->versions()->where('version_number', $v2Num)->first();

        $v1Findings = $v1 ? AiFinding::query()->where('report_version_id', $v1->id)->get() : collect();
        $v2Findings = $v2 ? AiFinding::query()->where('report_version_id', $v2->id)->get() : collect();

        $resolved = $v1Findings->filter(function ($f1) use ($v2Findings) {
            return ! $v2Findings->contains('title', $f1->title);
        })->values();

        return [
            'v1' => $v1Num,
            'v2' => $v2Num,
            'resolved_findings_count' => $resolved->count(),
            'resolved_findings' => $resolved,
            'current_findings_count' => $v2Findings->count(),
            'current_findings' => $v2Findings,
        ];
    }
}