<?php

namespace App\Services\AI;

use App\Enums\AiInsightType;
use App\Models\AiInsight;
use App\Models\Employee;
use App\Models\Report;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\Ai\Exceptions\AiProviderConfigurationException;
use App\Services\Reporting\ReportingService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase B: generates evidence-based, advisory AI insights from authorized DB
 * records and persists them with provenance metadata. Requires a configured
 * real (non-fake) AI provider for admin-facing generation; never fabricates.
 */
class AiInsightService
{
    public function __construct(
        private readonly ?AiProvider $provider = null,
        private readonly ?ReportingService $reportingService = null,
    ) {}

    public function generatePerformanceInsight(Employee $employee, string $period): AiInsight
    {
        return $this->generateAndPersist(AiInsightType::PERFORMANCE_INSIGHT, $employee, null, $period);
    }

    public function generateReportInsight(Report $report): AiInsight
    {
        return $this->generateAndPersist(AiInsightType::REPORT_INSIGHT, $report->employee, $report, now()->format('Y-m'));
    }

    public function generateTrendInsight(Employee $employee): AiInsight
    {
        return $this->generateAndPersist(AiInsightType::TREND_INSIGHT, $employee, null, now()->format('Y-m'));
    }

    public function generateAnomalyInsight(Employee $employee): AiInsight
    {
        return $this->generateAndPersist(AiInsightType::ANOMALY_INSIGHT, $employee, null, now()->format('Y-m'));
    }

    public function ensureRealProviderConfigured(): void
    {
        $provider = strtolower(trim((string) config('ai_reports.ai.provider', 'fake')));
        if ($provider === '' || $provider === 'fake') {
            throw new AiProviderConfigurationException('AI provider is not configured for live insight generation. Set AI_PROVIDER, AI_MODEL, AI_ENDPOINT, and AI_API_KEY in backend/.env, then run php artisan config:clear.');
        }
        $endpoint = trim((string) config('ai_reports.ai.http.endpoint', ''));
        $apiKey = trim((string) config('ai_reports.ai.http.api_key', ''));
        if ($endpoint === '') {
            throw new AiProviderConfigurationException('AI_ENDPOINT is not configured. Add it to backend/.env and run php artisan config:clear.');
        }
        if ($apiKey === '' && str_contains(strtolower($endpoint), 'openai')) {
            throw new AiProviderConfigurationException('AI_API_KEY is not configured for the OpenAI-compatible endpoint. Add it to backend/.env and run php artisan config:clear.');
        }
    }

    /**
     * @param  array<string,mixed>  $options
     */
    public function generateForAdmin(array $options, $adminUser): AiInsight
    {
        $this->ensureRealProviderConfigured();
        $type = AiInsightType::tryFrom((string) ($options['type'] ?? '')) ?? AiInsightType::PERFORMANCE_INSIGHT;
        $employee = null;
        $report = null;
        $period = (string) ($options['period'] ?? now()->format('Y-m'));
        if (! empty($options['employee_id'])) {
            $employee = Employee::query()->with(['department:id,name', 'position:id,name'])->find((int) $options['employee_id']);
        }
        if (! empty($options['report_id'])) {
            $report = Report::query()->with(['employee.department', 'employee.position', 'task', 'aiSummaries' => fn ($q) => $q->latest('id')->limit(1)])->find((int) $options['report_id']);
            $employee = $employee ?: $report?->employee;
            if ($report) {
                $type = AiInsightType::REPORT_INSIGHT;
            }
        }

        return $this->generateAndPersist($type, $employee, $report, $period, $adminUser, $options);
    }

    /**
     * @param  array<string,mixed>  $options
     */
    private function generateAndPersist(AiInsightType $type, ?Employee $employee, ?Report $report, string $period, $adminUser = null, array $options = []): AiInsight
    {
        $this->ensureRealProviderConfigured();
        if ($this->provider === null) {
            throw new AiProviderConfigurationException('AI provider service is unavailable for insight generation.');
        }

        $evidence = $this->collectEvidence($employee, $report, $period, $adminUser);
        $system = $this->systemPrompt();
        $userPrompt = $this->userPrompt($type, $employee, $report, $period, $evidence);

        $request = new AiRequest(
            model: (string) (config('ai_reports.ai.model') ?: 'gpt-4o-mini'),
            maxTokens: (int) config('ai_reports.ai.http.max_tokens', 1200),
            temperature: (float) config('ai_reports.ai.http.temperature', 0.2),
            expectJson: false,
            metadata: ['analysis_type' => 'admin_insight', 'insight_type' => $type->value],
        );

        $completion = $this->provider->complete($system, $userPrompt, $request);
        $content = trim($completion->text);
        if ($content === '') {
            throw new \RuntimeException('AI insight generation returned an empty response.');
        }

        $provenance = [
            'generator' => 'AdminAiInsightGenerationService/AiInsightService',
            'provider' => $this->providerName(),
            'model' => (string) config('ai_reports.ai.model', ''),
            'generated_by_user_id' => $adminUser?->id,
            'generated_at' => now()->toIso8601String(),
            'period' => $period,
            'evidence_source_types' => $evidence['source_types'],
            'source_record_refs' => $evidence['source_refs'],
            'missing_information' => $evidence['missing_information'],
            'human_verification_required' => true,
            'advisory_only' => true,
            'no_future_prediction' => true,
            'no_employment_decision' => true,
        ];
        if ($report) {
            $provenance['report_id'] = $report->id;
        }

        return AiInsight::create([
            'employee_id' => $employee?->id,
            'department_id' => $employee?->department_id ?? $employee?->department?->id,
            'type' => $type,
            'content' => $content."\n\n[Verification required: AI-generated advisory insight based on authorized records. Verify source references before administrative action.]",
            'metadata' => [
                'provenance' => $provenance,
                'source' => $provenance,
                'options' => array_intersect_key($options, array_flip(['type', 'employee_id', 'report_id', 'period', 'department_id'])),
            ],
            'generated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function collectEvidence(?Employee $employee, ?Report $report, string $period, $adminUser): array
    {
        $refs = [];
        $types = [];
        $missing = [];
        $records = [];

        if ($report) {
            $report->loadMissing(['employee.department', 'employee.position', 'task', 'aiSummaries' => fn ($q) => $q->latest('id')->limit(1)]);
            $types[] = 'report';
            $refs[] = ['type' => 'report', 'id' => $report->id];
            $records['report'] = [
                'id' => $report->id,
                'title' => $report->title,
                'status' => $report->status instanceof \BackedEnum ? $report->status->value : $report->status,
                'reporting_period' => $report->reporting_period,
                'description' => Str::limit((string) ($report->description ?? ''), 800),
                'content' => Str::limit((string) ($report->content ?? ''), 1200),
                'accomplishments' => Str::limit((string) ($report->accomplishments ?? ''), 800),
                'challenges' => Str::limit((string) ($report->challenges_encountered ?? ''), 500),
                'next_plans' => Str::limit((string) ($report->next_plans ?? ''), 500),
                'employee' => $report->employee ? ['id' => $report->employee->id, 'name' => trim(($report->employee->first_name ?? '').' '.($report->employee->last_name ?? ''))] : null,
                'task' => $report->task ? ['id' => $report->task->id, 'title' => $report->task->title] : null,
            ];
            $hasContent = trim((string) ($report->description ?? '').($report->content ?? '').($report->accomplishments ?? '')) !== '';
            if (! $hasContent) {
                $missing[] = 'The selected report has limited or missing narrative content fields.';
            }
        }

        if ($employee) {
            $types[] = 'employee';
            $refs[] = ['type' => 'employee', 'id' => $employee->id];
            $records['employee'] = [
                'id' => $employee->id,
                'name' => trim(collect([$employee->first_name, $employee->middle_name, $employee->last_name])->filter()->implode(' ')),
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name ?? $employee->position?->title ?? null,
            ];
            if ($this->reportingService && $adminUser) {
                try {
                    [$year, $month] = $this->parsePeriod($period);
                    $progress = $this->reportingService->generateEmployeeReport((int) $employee->id, ['year' => $year, 'month' => $month], $adminUser);
                    $records['progress'] = $progress;
                    $types[] = 'employee_progress_report';
                    $refs[] = ['type' => 'employee_progress_report', 'id' => $employee->id, 'period' => $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT)];
                } catch (Throwable $e) {
                    $missing[] = 'Employee progress report could not be generated for evidence: '.Str::limit($e->getMessage(), 140);
                }
            }
            $recentReports = Report::query()->where('employee_id', $employee->id)->latest('id')->limit(5)->get(['id', 'title', 'status', 'reporting_period', 'is_late', 'ai_summary_status']);
            $records['recent_reports'] = $recentReports->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'status' => $r->status instanceof \BackedEnum ? $r->status->value : $r->status, 'is_late' => (bool) $r->is_late])->all();
            if ($recentReports->isEmpty()) {
                $missing[] = 'No recent report records found for this employee.';
            }
            $types[] = 'reports';
        }

        if ($records === [] || (empty($records['employee']) && empty($records['report']))) {
            $missing[] = 'No specific employee or report evidence was available for insight generation; provide a valid employee_id and/or report_id.';
            throw new \InvalidArgumentException(implode(' ', $missing));
        }

        return [
            'records' => $records,
            'source_types' => array_values(array_unique($types)),
            'source_refs' => $refs,
            'missing_information' => array_values(array_unique($missing)),
        ];
    }

    /** @return array{0:int,1:int} */
    private function parsePeriod(string $period): array
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', trim($period), $m)) {
            return [(int) $m[1], max(1, min(12, (int) $m[2]))];
        }

        return [(int) now()->year, (int) now()->month];
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'You are generating an advisory AI insight for JPC AIDE (Admin).',
            'Use ONLY the authorized evidence provided. Never invent records, IDs, dates, metrics, names, or events.',
            'Include: concise insight summary, evidence-based observations, recommendations/advisory follow-ups, and missing or incomplete information.',
            'Recommendations must require human/Admin verification and must not be automatic employment decisions.',
            'Never predict future employee performance. Never make hiring/firing/promotion/demotion/discipline/salary decisions.',
            'Cite source record references (report IDs, employee IDs, period) where applicable.',
        ]);
    }

    private function userPrompt(AiInsightType $type, ?Employee $employee, ?Report $report, string $period, array $evidence): string
    {
        $label = ucwords(strtolower(str_replace('_', ' ', $type->name)));
        $subject = $report ? 'Report #'.$report->id : ($employee ? 'Employee #'.$employee->id : 'Authorized scope');
        $missing = $evidence['missing_information'] ?? [];
        $missingText = $missing ? implode("\n- ", array_merge([''], $missing)) : "\n- None identified from available evidence.";

        return implode("\n", [
            "Generate a {$label} for {$subject} (period/context: {$period}).",
            '',
            'Authorized evidence JSON:',
            json_encode($evidence['records'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '',
            'Missing/incomplete information to account for:'.$missingText,
            '',
            'Output a clear, professional advisory insight grounded in evidence. Include source references and a human-verification note.',
        ]);
    }

    private function providerName(): string
    {
        try {
            return $this->provider ? $this->provider->name() : (string) config('ai_reports.ai.provider', 'unknown');
        } catch (Throwable) {
            return (string) config('ai_reports.ai.provider', 'unknown');
        }
    }
}
