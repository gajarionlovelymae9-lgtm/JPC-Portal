<?php

namespace App\Services\AI;

use App\Enums\AiIntent;
use App\Enums\AiMessageRole;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiInsight;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use App\Services\AI\Prompts\AiChatSystemPrompt;
use App\Services\AI\Tools\AiToolInterface;
use App\Services\AI\Tools\RoleAwareAiToolInterface;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\Ai\Exceptions\AiProviderConfigurationException;
use App\Services\AiReports\Ai\Exceptions\AiProviderException;
use App\Services\Reporting\ReportingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin-only JPC AIDE assistant (Phase B: NLP report summarization/progress
 * analysis/insights questions, grounded in authorized records with source refs).
 *
 * This service never treats the deterministic FakeAiProvider as a successful
 * production AI response. It requires a configured, non-fake AI provider and
 * returns clear configuration errors when unavailable.
 */
class AdminAiAssistantService
{
    private const HISTORY_LIMIT = 12;
    private const MAX_HISTORY_CHARS = 10000;
    private const MAX_EVIDENCE_CHARS = 14000;
    private const MAX_REPORTS = 5;
    private const MAX_NAME_MATCHES = 5;
    private const PLACEHOLDER_TITLES = ['', 'new chat', 'new conversation', 'untitled', 'untitled conversation'];

    /** @var array<string, list<string>> */
    private const INTENT_TOOL_MAP = [
        AiIntent::MANAGEMENT_SUMMARY->value => ['GetManagementSummaryTool'],
        AiIntent::MONTHLY_ACHIEVEMENTS->value => ['GetMonthlyPerformanceReportTool'],
        AiIntent::EMPLOYEE_PERFORMANCE->value => ['GetEmployeeProgressReportTool'],
        AiIntent::DEPARTMENT_PERFORMANCE->value => ['GetDepartmentReportTool'],
        AiIntent::TOP_PERFORMERS->value => ['GetRankingTool'],
        AiIntent::PERFORMANCE_TREND->value => ['GetPerformanceTrendTool'],
        AiIntent::TARGET_PROGRESS->value => ['GetTargetAnalyticsTool'],
        AiIntent::TARGET_ACHIEVEMENT->value => ['GetTargetAnalyticsTool'],
        AiIntent::LATE_REPORTS->value => ['GetLateReportAnalyticsTool'],
        AiIntent::OVERDUE_TASKS->value => ['GetOverdueTasksTool'],
        AiIntent::TASK_STATISTICS->value => ['GetTaskMonitoringSummaryTool'],
        AiIntent::TASK_COMPLETION_RATE->value => ['GetTaskMonitoringSummaryTool'],
        AiIntent::PENDING_REPORTS->value => ['GetLateReportAnalyticsTool', 'GetManagementSummaryTool'],
        AiIntent::DATA_QUALITY_STATISTICS->value => ['GetDataQualityStatisticsTool'],
        AiIntent::DATA_QUALITY_ISSUES->value => ['GetDataQualityIssuesTool'],
        AiIntent::CRITICAL_DATA_QUALITY_ISSUES->value => ['GetCriticalDataQualityIssuesTool'],
        AiIntent::DATA_QUALITY_TRENDS->value => ['GetDataQualityTrendsTool'],
    ];

    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiToolRegistry $toolRegistry,
        private readonly AiIntentDetector $intentDetector,
        private readonly ReportingService $reportingService,
        private readonly ?AiChatSystemPrompt $systemPrompt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function chat(User $user, ?int $conversationId, string $message, ?string $languagePreference = null): array
    {
        $this->assertAdmin($user);
        $this->assertRealProviderConfigured();

        $language = $this->resolveLanguage($user, $message, $languagePreference);
        $conversation = $this->resolveConversation($user, $conversationId, $message);

        $userMessage = $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $message,
            'is_visible' => true,
        ]);
        $conversation->forceFill(['updated_at' => now()])->save();

        try {
            $evidence = $this->gatherEvidence($user, $message);
            $assistantText = $this->generateReply($user, $conversation, $message, $language, $evidence);
            $assistantMessage = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $assistantText,
                'intent' => 'admin_assistant',
                // Internal-only provenance/advisory metadata. It is stored for
                // audit, logging and source-verification purposes and is never
                // appended to the user-facing `content`.
                'tool_results' => $this->advisoryMetadata($evidence, $language),
                'is_visible' => true,
            ]);

            return $this->response($conversation, $assistantMessage, $userMessage, true);
        } catch (Throwable $e) {
            Log::warning('Admin AI assistant chat failed', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'language' => $language,
            ]);

            $assistantMessage = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $this->failureMessage($e, $language),
                'intent' => 'admin_assistant_error',
                'tool_results' => ['error' => mb_substr($e->getMessage(), 0, 500), 'language' => $language],
                'is_visible' => true,
            ]);

            return $this->response($conversation, $assistantMessage, $userMessage, false);
        }
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->hasAnyRole(['admin'])) {
            throw new AuthorizationException('The Admin AI assistant is available only to authorized administrators.');
        }
    }

    public function ensureRealProviderConfigured(): void
    {
        $provider = strtolower(trim((string) config('ai_reports.ai.provider', 'fake')));

        if ($provider === '' || $provider === 'fake') {
            throw new AiProviderConfigurationException('AI provider is not configured for live responses. Set AI_PROVIDER, AI_MODEL, AI_ENDPOINT, and AI_API_KEY in backend/.env, then run php artisan config:clear.');
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

    private function assertRealProviderConfigured(): void
    {
        $this->ensureRealProviderConfigured();
    }

    private function providerName(): string
    {
        try {
            return $this->provider->name();
        } catch (Throwable) {
            return (string) config('ai_reports.ai.provider', 'unknown');
        }
    }

    /**
     * Phase B: gather multiple authorized evidence sources with explicit
     * source references and missing-information reporting.
     *
     * @return array<string, mixed>
     */
    private function gatherEvidence(User $user, string $message): array
    {
        $detected = $this->intentDetector->detect($message);
        $intent = (string) ($detected['intent'] ?? 'DEFAULT');
        $filters = is_array($detected['filters'] ?? null) ? $detected['filters'] : [];
        $filters['year'] = (int) ($filters['year'] ?? now()->year);
        $filters['month'] = (int) ($filters['month'] ?? now()->month);

        $sources = [];
        $sourceRecords = [];
        $records = [];
        $missing = [];

        $reportIds = $this->extractReferencedReportIds($message);
        $employee = $this->extractReferencedEmployee($message);
        $asksReportSummary = $this->isReportSummaryQuestion($message, $intent);
        $asksProgress = $this->isProgressQuestion($message, $intent);
        $asksInsights = $this->isInsightQuestion($message);

        if ($reportIds !== []) {
            $reportEvidence = $this->gatherReportEvidence($reportIds, $asksReportSummary);
            $records['reports'] = $reportEvidence['reports'];
            $sources = array_merge($sources, $reportEvidence['sources']);
            $sourceRecords = array_merge($sourceRecords, $reportEvidence['source_records']);
            $missing = array_merge($missing, $reportEvidence['missing_information']);
        }

        if ($employee !== null) {
            $records['referenced_employee'] = $employee['summary'];
            $sourceRecords[] = $employee['source_record'];
            $sources[] = 'employees';

            if ($asksProgress || $asksReportSummary || $reportIds === []) {
                $progress = $this->gatherEmployeeProgressEvidence($user, $employee, $filters);
                if ($progress !== null) {
                    $records['employee_progress'] = $progress['data'];
                    $sources[] = $progress['source'];
                    $sourceRecords[] = $progress['source_record'];
                    $missing = array_merge($missing, $progress['missing_information']);
                }

                $empReports = $this->gatherEmployeeRecentReportsEvidence($employee['id']);
                $records['employee_recent_reports'] = $empReports['reports'];
                $sources = array_merge($sources, $empReports['sources']);
                $sourceRecords = array_merge($sourceRecords, $empReports['source_records']);
                $missing = array_merge($missing, $empReports['missing_information']);
            }
        } elseif ($asksProgress && $reportIds === []) {
            $missing[] = 'No specific employee was identified in the question. Provide an employee name, employee ID, or employee number for employee-specific progress analysis; I can still provide authorized management-level summaries when available.';
        }

        if ($asksInsights) {
            $insightEvidence = $this->gatherInsightEvidence($employee['id'] ?? null);
            $records['ai_insights'] = $insightEvidence['data'];
            $sources = array_merge($sources, $insightEvidence['sources']);
            $sourceRecords = array_merge($sourceRecords, $insightEvidence['source_records']);
            $missing = array_merge($missing, $insightEvidence['missing_information']);
        }

        $tool = $this->resolveTool($intent, $user, $reportIds !== [], $employee !== null);
        if ($tool !== null) {
            $permission = $tool->requiredPermission();
            if ($permission === null || $user->hasPermission($permission)) {
                try {
                    $params = $this->buildParams($tool->name(), $filters, $message, $employee['id'] ?? null);
                    $result = $tool->execute($params, $user);
                    $records['tool:'.$tool->name()] = $result;
                    $sources[] = $tool->name();
                    $sourceRecords[] = [
                        'source_type' => 'analytics_tool',
                        'source_id' => $tool->name(),
                        'label' => $tool->name(),
                        'period' => $filters['year'].'-'.str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT),
                    ];
                } catch (AuthorizationException|Throwable $e) {
                    $missing[] = 'Evidence tool '.$tool->name().' could not be used: '.$this->safeMissingText($e->getMessage());
                    Log::notice('Admin AI evidence tool skipped', ['tool' => $tool->name(), 'error' => $e->getMessage()]);
                }
            } else {
                $missing[] = 'Permission "'.$permission.'" is required for tool '.$tool->name().'; that evidence was not included.';
            }
        }

        if ($records === []) {
            $missing[] = 'No specific authorized record, report ID, employee reference, or analytics tool matched this question. Ask with a report ID, employee name/ID, department, or reporting period for grounded analysis.';
        }

        $sources = array_values(array_unique(array_filter($sources)));
        $missing = array_values(array_unique(array_filter($missing)));

        $payload = [
            'grounding' => [
                'authorized_scope' => 'admin',
                'actual_database_records_only' => true,
                'fabrication_allowed' => false,
                'human_verification_required' => true,
            ],
            'detected_intent' => $intent,
            'period' => ['year' => $filters['year'], 'month' => $filters['month']],
            'question_flags' => [
                'asks_report_summary' => $asksReportSummary,
                'asks_progress_analysis' => $asksProgress,
                'asks_insights_or_recommendations' => $asksInsights,
            ],
            'source_records' => $sourceRecords,
            'missing_information' => $missing,
            'records' => $records,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded)) {
            $encoded = '{"available":false,"missing_information":["Evidence could not be serialized."]}';
        }
        if (strlen($encoded) > self::MAX_EVIDENCE_CHARS) {
            $encoded = substr($encoded, 0, self::MAX_EVIDENCE_CHARS)."\n...[evidence truncated for safety; ask for a specific report ID or narrower period]";
            $missing[] = 'Evidence payload was truncated for safety; request a specific report ID, employee, or narrower period.';
        }

        return [
            'intent' => $intent,
            'period' => ['year' => $filters['year'], 'month' => $filters['month']],
            'sources' => $sources,
            'source_records' => $sourceRecords,
            'missing_information' => $missing,
            'payload' => $encoded,
        ];
    }

    /** @return list<int> */
    private function extractReferencedReportIds(string $message): array
    {
        $ids = [];
        $patterns = [
            '/\breport\s*(?:id\s*)?#\s*(\d+)\b/i',
            '/\breport\s*id\s*[:=]?\s*(\d+)\b/i',
            '/\breport\s+(\d{1,10})\b/i',
            '/\b(?:submission|record)\s*#\s*(\d+)\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $message, $m)) {
                foreach ($m[1] as $id) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    /**
     * @return array{id:int, summary:array<string,mixed>, source_record:array<string,mixed>}|null
     */
    private function extractReferencedEmployee(string $message): ?array
    {
        if (preg_match('/\bemployee\s*(?:id\s*)?#?\s*(\d+)\b/i', $message, $m)) {
            $employee = Employee::query()->with(['department:id,name', 'position:id,name'])->find((int) $m[1]);
            if ($employee) {
                return $this->employeeContext($employee, 'matched by employee ID');
            }
        }

        if (preg_match('/\bemp(?:loyee)?\s*(?:no|number|code)\s*[:#]?\s*([A-Z0-9\-]+)\b/i', $message, $m)) {
            $employee = Employee::query()->with(['department:id,name', 'position:id,name'])
                ->where('employee_number', trim($m[1]))
                ->first();
            if ($employee) {
                return $this->employeeContext($employee, 'matched by employee number');
            }
        }

        $tokens = $this->nameTokens($message);
        if ($tokens === []) {
            return null;
        }

        $query = Employee::query()->with(['department:id,name', 'position:id,name']);
        foreach ($tokens as $token) {
            $like = '%'.$token.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like);
            });
        }
        $matches = $query->limit(self::MAX_NAME_MATCHES)->get();
        if ($matches->count() === 1) {
            return $this->employeeContext($matches->first(), 'matched by name tokens in question');
        }

        return null;
    }

    /** @return list<string> */
    private function nameTokens(string $message): array
    {
        $stop = ['summarize', 'summary', 'report', 'reports', 'employee', 'progress', 'analysis', 'analyze', 'insight', 'insights', 'recommendation', 'recommendations', 'latest', 'recent', 'performance', 'for', 'about', 'show', 'give', 'please', 'admin', 'status', 'monthly', 'annual'];
        preg_match_all('/\b([A-Za-z][A-Za-z\.\'\-]{1,40})\b/u', $message, $m);
        $tokens = [];
        foreach ($m[1] ?? [] as $token) {
            $lower = mb_strtolower($token);
            if (in_array($lower, $stop, true) || is_numeric($token) || mb_strlen($token) < 2) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_slice(array_unique($tokens), 0, 4));
    }

    /**
     * @return array{id:int, summary:array<string,mixed>, source_record:array<string,mixed>}
     */
    private function employeeContext(Employee $employee, string $matchNote): array
    {
        $name = trim(collect([$employee->first_name, $employee->middle_name, $employee->last_name, $employee->suffix])->filter()->implode(' '));

        return [
            'id' => (int) $employee->id,
            'summary' => [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $name !== '' ? $name : null,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name ?? $employee->position?->title ?? null,
                'employment_status' => $employee->employment_status ?? null,
                'match_note' => $matchNote,
            ],
            'source_record' => [
                'source_type' => 'employee',
                'source_id' => $employee->id,
                'label' => 'Employee #'.$employee->id.($name !== '' ? ' '.$name : ''),
                'match_note' => $matchNote,
            ],
        ];
    }

    private function isReportSummaryQuestion(string $message, string $intent): bool
    {
        $t = mb_strtolower($message);
        return $intent === AiIntent::REPORT_AI_SUMMARY->value
            || str_contains($t, 'summarize report')
            || str_contains($t, 'summarise report')
            || str_contains($t, 'summary of report')
            || str_contains($t, 'report summary')
            || (str_contains($t, 'summar') && str_contains($t, 'report'));
    }

    private function isProgressQuestion(string $message, string $intent): bool
    {
        $t = mb_strtolower($message);
        return in_array($intent, [AiIntent::EMPLOYEE_PERFORMANCE->value, AiIntent::PERFORMANCE_TREND->value, AiIntent::TARGET_PROGRESS->value, AiIntent::MONTHLY_ACHIEVEMENTS->value, AiIntent::TASK_STATISTICS->value], true)
            || str_contains($t, 'progress')
            || str_contains($t, 'performance');
    }

    private function isInsightQuestion(string $message): bool
    {
        $t = mb_strtolower($message);
        return str_contains($t, 'insight') || str_contains($t, 'recommend') || str_contains($t, 'analysis') || str_contains($t, 'what should');
    }

    /**
     * @param  list<int>  $reportIds
     * @return array<string, mixed>
     */
    private function gatherReportEvidence(array $reportIds, bool $includeFullContent): array
    {
        $reports = Report::query()
            ->with(['employee:id,first_name,last_name,employee_number,department_id', 'employee.department:id,name', 'task:id,title', 'aiSummaries' => fn ($q) => $q->latest('id')->limit(1)])
            ->whereIn('id', array_slice($reportIds, 0, self::MAX_REPORTS))
            ->get();

        $foundIds = $reports->pluck('id')->map(fn ($id) => (int) $id)->all();
        $missing = [];
        foreach ($reportIds as $id) {
            if (! in_array($id, $foundIds, true)) {
                $missing[] = 'Report #'.$id.' was not found in authorized records.';
            }
        }

        $items = [];
        $sources = ['reports'];
        $sourceRecords = [];
        foreach ($reports as $report) {
            $contentParts = [];
            foreach (['description', 'content', 'accomplishments', 'completed_tasks_summary', 'challenges_encountered', 'next_plans', 'remarks'] as $field) {
                $value = trim((string) ($report->{$field} ?? ''));
                if ($value !== '') {
                    $contentParts[$field] = Str::limit($value, $includeFullContent ? 2000 : 350);
                }
            }
            $latestSummary = $report->aiSummaries->first();
            $items[] = [
                'report_id' => $report->id,
                'title' => $report->title,
                'status' => $this->enumValue($report->status),
                'report_type' => $report->report_type,
                'reporting_period' => $report->reporting_period,
                'submission_date' => $this->dateValue($report->submission_date ?? $report->submitted_at),
                'deadline' => $this->dateValue($report->deadline),
                'is_late' => (bool) ($report->is_late ?? false),
                'employee' => $report->employee ? [
                    'id' => $report->employee->id,
                    'name' => trim(($report->employee->first_name ?? '').' '.($report->employee->last_name ?? '')),
                    'employee_number' => $report->employee->employee_number ?? null,
                    'department' => $report->employee->department?->name,
                ] : null,
                'task' => $report->task ? ['id' => $report->task->id, 'title' => $report->task->title] : null,
                'stored_ai_summary_status' => $report->ai_summary_status,
                'stored_ai_summary_preview' => $report->ai_summary ? Str::limit((string) $report->ai_summary, 400) : null,
                'latest_ai_summary_record' => $latestSummary ? [
                    'id' => $latestSummary->id,
                    'summary' => Str::limit((string) ($latestSummary->summary ?? ''), 500),
                    'created_at' => $this->dateValue($latestSummary->created_at),
                ] : null,
                'content_fields_present' => array_keys($contentParts),
                'content_excerpt' => $contentParts,
            ];
            $sourceRecords[] = [
                'source_type' => 'report',
                'source_id' => $report->id,
                'label' => 'Report #'.$report->id.($report->title ? ': '.$report->title : ''),
                'status' => $this->enumValue($report->status),
                'period' => $report->reporting_period ?: $this->dateValue($report->submission_date ?? $report->submitted_at),
            ];
            if ($contentParts === []) {
                $missing[] = 'Report #'.$report->id.' has no substantial narrative/content fields available for summarization.';
            }
            if (! $report->ai_summary && ! $latestSummary) {
                $missing[] = 'Report #'.$report->id.' has no stored AI summary record; analysis below is based on available report fields only.';
            }
        }

        return ['reports' => $items, 'sources' => array_values(array_unique($sources)), 'source_records' => $sourceRecords, 'missing_information' => array_values(array_unique($missing))];
    }

    /**
     * @param  array<string,mixed>  $employee
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>|null
     */
    private function gatherEmployeeProgressEvidence(User $user, array $employee, array $filters): ?array
    {
        try {
            $data = $this->reportingService->generateEmployeeReport((int) $employee['id'], $filters, $user);
            $missing = [];
            if (($data['tasks']['total_assigned'] ?? 0) === 0 && ($data['reports']['total_submitted'] ?? 0) === 0) {
                $missing[] = 'No task/report activity records were found for this employee in the selected period ('.$filters['year'].'-'.$filters['month'].').';
            }
            if (($data['performance']['has_data'] ?? false) === false) {
                $missing[] = 'No performance snapshot/record was available for the selected period; progress metrics may be computed from available records or incomplete.';
            }

            return [
                'source' => 'ReportingService.generateEmployeeReport',
                'source_record' => [
                    'source_type' => 'employee_progress_report',
                    'source_id' => $employee['id'],
                    'label' => 'Employee progress report: '.$employee['summary']['name'] ?? ('Employee #'.$employee['id']),
                    'period' => $filters['year'].'-'.str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT),
                ],
                'data' => $data,
                'missing_information' => $missing,
            ];
        } catch (Throwable $e) {
            Log::notice('Admin AI employee progress evidence failed', ['employee_id' => $employee['id'] ?? null, 'error' => $e->getMessage()]);

            return [
                'source' => 'ReportingService.generateEmployeeReport',
                'source_record' => [
                    'source_type' => 'employee_progress_report',
                    'source_id' => $employee['id'],
                    'label' => 'Employee progress report (unavailable)',
                ],
                'data' => ['available' => false],
                'missing_information' => ['Employee progress report could not be generated: '.$this->safeMissingText($e->getMessage())],
            ];
        }
    }

    /** @return array<string,mixed> */
    private function gatherEmployeeRecentReportsEvidence(int $employeeId): array
    {
        $reports = Report::query()
            ->where('employee_id', $employeeId)
            ->latest('submission_date')
            ->latest('submitted_at')
            ->latest('id')
            ->limit(self::MAX_REPORTS)
            ->get(['id', 'title', 'status', 'reporting_period', 'submission_date', 'submitted_at', 'deadline', 'is_late', 'ai_summary_status']);

        $items = $reports->map(fn (Report $r) => [
            'report_id' => $r->id,
            'title' => $r->title,
            'status' => $this->enumValue($r->status),
            'reporting_period' => $r->reporting_period,
            'submission_date' => $this->dateValue($r->submission_date ?? $r->submitted_at),
            'is_late' => (bool) ($r->is_late ?? false),
            'ai_summary_status' => $r->ai_summary_status,
        ])->all();

        $sourceRecords = array_map(fn (array $item) => [
            'source_type' => 'report',
            'source_id' => $item['report_id'],
            'label' => 'Recent report #'.$item['report_id'].($item['title'] ? ': '.$item['title'] : ''),
            'status' => $item['status'],
            'period' => $item['reporting_period'] ?: $item['submission_date'],
        ], $items);

        return [
            'reports' => $items,
            'sources' => $items ? ['reports'] : [],
            'source_records' => $sourceRecords,
            'missing_information' => $items ? [] : ['No report records were found for this employee.'],
        ];
    }

    /** @return array<string,mixed> */
    private function gatherInsightEvidence(?int $employeeId): array
    {
        $query = AiInsight::query()->latest('generated_at')->latest('id')->limit(10);
        if ($employeeId) {
            $query->where(function ($q) use ($employeeId): void {
                $q->where('employee_id', $employeeId)->orWhereNull('employee_id');
            });
        }
        $insights = $query->get(['id', 'employee_id', 'department_id', 'type', 'content', 'metadata', 'generated_at']);
        $items = $insights->map(fn (AiInsight $i) => [
            'insight_id' => $i->id,
            'type' => $this->enumValue($i->type),
            'scope' => $i->employee_id ? 'employee' : ($i->department_id ? 'department' : 'institution'),
            'employee_id' => $i->employee_id,
            'department_id' => $i->department_id,
            'generated_at' => $this->dateValue($i->generated_at),
            'content_preview' => Str::limit((string) $i->content, 500),
            'provenance' => is_array($i->metadata) ? ($i->metadata['provenance'] ?? $i->metadata['source'] ?? null) : null,
        ])->all();

        $sourceRecords = array_map(fn (array $item) => [
            'source_type' => 'ai_insight',
            'source_id' => $item['insight_id'],
            'label' => 'AI insight #'.$item['insight_id'].' ('.$item['type'].')',
            'generated_at' => $item['generated_at'],
        ], $items);

        return [
            'data' => $items,
            'sources' => $items ? ['ai_insights'] : [],
            'source_records' => $sourceRecords,
            'missing_information' => $items ? ['Stored AI insights are advisory records; verify them against current source records before administrative action.'] : ['No stored AI insight records matched this scope. Insights can be generated from authorized records when requested through the admin insights workflow.'],
        ];
    }

    private function resolveTool(string $intent, User $user, bool $hasReportRef, bool $hasEmployeeRef): ?AiToolInterface
    {
        if ($hasReportRef && in_array($intent, ['DEFAULT', AiIntent::REPORT_AI_SUMMARY->value], true)) {
            return null;
        }
        foreach (self::INTENT_TOOL_MAP[$intent] ?? [] as $toolName) {
            $tool = $this->toolRegistry->find($toolName);
            if ($tool === null) {
                continue;
            }
            if ($tool instanceof RoleAwareAiToolInterface && ! $tool->supports($user)) {
                continue;
            }
            if ($hasEmployeeRef && $toolName === 'GetEmployeeProgressReportTool') {
                return $tool;
            }
            if (! $hasEmployeeRef || $toolName !== 'GetEmployeeProgressReportTool') {
                return $tool;
            }
        }
        if ($hasEmployeeRef) {
            $tool = $this->toolRegistry->find('GetEmployeeProgressReportTool');
            if ($tool && (!$tool instanceof RoleAwareAiToolInterface || $tool->supports($user))) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildParams(string $toolName, array $filters, string $message, ?int $employeeId = null): array
    {
        $params = [
            'year' => $filters['year'] ?? now()->year,
            'month' => $filters['month'] ?? now()->month,
        ];
        if ($employeeId && str_contains($toolName, 'EmployeeProgress')) {
            $params['employee_id'] = $employeeId;
        }
        if (str_contains($toolName, 'Trend')) {
            $params['months'] = $filters['months'] ?? 6;
        }
        if (str_contains($toolName, 'Ranking') || preg_match('/top\s+(\d+)/i', $message, $m)) {
            $params['limit'] = isset($m[1]) ? (int) $m[1] : 5;
        }

        return $params;
    }

    private function generateReply(User $user, AiChatConversation $conversation, string $message, string $language, array $evidence): string
    {
        $system = $this->systemPrompt($user, $language);
        $history = $this->historyTranscript($conversation);
        $userPrompt = $this->userPrompt($history, $message, $language, $evidence);

        $request = new AiRequest(
            model: (string) (config('ai_reports.ai.model') ?: 'gpt-4o-mini'),
            maxTokens: (int) config('ai_reports.ai.http.max_tokens', 1600),
            temperature: (float) config('ai_reports.ai.http.temperature', 0.2),
            expectJson: false,
            metadata: ['analysis_type' => 'admin_chat', 'scope' => 'admin_assistant_phase_b', 'language' => $language],
        );

        $completion = $this->provider->complete($system, $userPrompt, $request);
        $text = trim($completion->text);

        if ($text === '') {
            throw new AiProviderException('The AI assistant returned an empty response.');
        }

        // The user-facing message is the model's natural-language answer only.
        // Provider/model/period/source provenance lives in `tool_results`
        // (see advisoryMetadata()) and must never be appended here.
        return $text;
    }

    /**
     * Build the internal advisory/provenance metadata stored alongside an
     * assistant message.
     *
     * Kept separately from the message content so the API/UI can render only
     * the intended answer while logs, audits and source-verification tooling
     * still have access to provider, model, period, source records and the
     * AI safety posture.
     *
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function advisoryMetadata(array $evidence, string $language): array
    {
        $period = is_array($evidence['period'] ?? null) ? $evidence['period'] : [];
        $year = (int) ($period['year'] ?? now()->year);
        $month = (int) ($period['month'] ?? now()->month);
        $sourceRecords = is_array($evidence['source_records'] ?? null) ? $evidence['source_records'] : [];

        return [
            'provider' => $this->providerName(),
            'model' => (string) config('ai_reports.ai.model', ''),
            'language' => $language,
            'evidence_sources' => $evidence['sources'] ?? [],
            'source_records' => $sourceRecords,
            'source_labels' => $this->humanReadableSourceLabels($sourceRecords),
            'missing_information' => $evidence['missing_information'] ?? [],
            'period' => ['year' => $year, 'month' => $month],
            'period_label' => now()->setDate($year, $month, 1)->format('F Y'),
            'advisory' => [
                'ai_generated' => true,
                'human_verification_required' => true,
                'fabrication_allowed' => false,
                'future_performance_predictions' => false,
                'automatic_employment_decisions' => false,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Convert internal source records into plain-language labels for internal
     * provenance display. Raw tool class names are mapped to descriptive names.
     *
     * @param  array<int, mixed>  $records
     * @return list<string>
     */
    private function humanReadableSourceLabels(array $records): array
    {
        $toolLabels = [
            'GetDepartmentReportTool' => 'Department performance report',
            'GetManagementSummaryTool' => 'Management summary',
            'GetMonthlyPerformanceReportTool' => 'Monthly performance report',
            'GetEmployeeProgressReportTool' => 'Employee progress report',
            'GetRankingTool' => 'Top performers ranking',
            'GetPerformanceTrendTool' => 'Performance trend',
            'GetTargetAnalyticsTool' => 'Target analytics',
            'GetLateReportAnalyticsTool' => 'Late/pending report analytics',
            'GetOverdueTasksTool' => 'Overdue tasks',
            'GetTaskMonitoringSummaryTool' => 'Task monitoring summary',
            'GetDataQualityStatisticsTool' => 'Data quality statistics',
            'GetDataQualityIssuesTool' => 'Data quality issues',
            'GetCriticalDataQualityIssuesTool' => 'Critical data quality issues',
            'GetDataQualityTrendsTool' => 'Data quality trends',
        ];

        $labels = [];
        foreach (array_slice($records, 0, 12) as $record) {
            if (! is_array($record)) {
                continue;
            }
            $type = (string) ($record['source_type'] ?? '');
            $id = $record['source_id'] ?? '';
            $label = trim((string) ($record['label'] ?? ''));

            if ($type === 'analytics_tool') {
                $toolName = (string) ($id !== '' ? $id : $label);
                $labels[] = $toolLabels[$toolName] ?? 'Analytics result';
                continue;
            }

            if ($label !== '') {
                $labels[] = $label;
                continue;
            }

            $labels[] = match ($type) {
                'report' => 'Report #'.$id,
                'employee' => 'Employee #'.$id,
                'employee_progress_report' => 'Employee progress report',
                'ai_insight' => 'AI insight #'.$id,
                default => '',
            };
        }

        return array_values(array_unique(array_filter($labels)));
    }

    private function systemPrompt(User $user, string $language): string
    {
        $base = ($this->systemPrompt ?? new AiChatSystemPrompt())->systemPrompt($user);
        $lang = $language === 'tagalog' ? 'filipino' : $language;
        $langRule = match ($lang) {
            'filipino' => 'Respond in clear, professional Filipino/Tagalog unless the user asks for another language.',
            'taglish' => 'Respond in natural, professional Taglish unless the user asks for another language.',
            'english' => 'Respond in clear, professional English unless the user asks for another language.',
            default => 'Follow the user\'s language when possible (English, Filipino/Tagalog, Taglish).',
        };

        return implode("\n", [
            $base,
            '',
            'ADMIN ASSISTANT MODE (authorized administrator):',
            '- Use ONLY the authorized evidence payload and conversation context provided. Never invent employees, departments, tasks, reports, dates, percentages, scores, IDs, findings, or events.',
            '- Perform NLP-based report summarization, employee progress analysis, and AI-generated insights/recommendations ONLY from actual evidence records. When evidence is insufficient, say so explicitly.',
            '- Write clear, natural, well-structured answers using short paragraphs and, where useful, bullet points.',
            '- Refer to evidence in plain language that a non-technical administrator understands (for example "Report #12", "Employee progress records", "Department performance report", "November 2026").',
            '- Never mention internal tool class names, internal source keys, JSON field names, Phase labels, database column names, or backend implementation details in the answer.',
            '- Do not append system footers, metadata blocks, raw source-reference dumps, or debugging instructions to the answer; the application already presents AI-advisory and human-verification notices in the interface.',
            '- Always include a clear "Missing or incomplete information" section/list when any relevant record, field, summary, period data, or match is missing.',
            '- Recommendations must be advisory, evidence-based, and subject to human/Admin verification. Do not present recommendations as final decisions.',
            '- NEVER predict future employee performance.',
            '- NEVER make or recommend automatic employment decisions (hiring, firing, promotion, demotion, discipline, salary decisions).',
            '- If asked to summarize a report, summarize the actual report content/metadata provided; do not fabricate missing sections.',
            '- Do not expose secrets, credentials, tokens, or unnecessary confidential fields.',
            '- '.$langRule,
        ]);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function userPrompt(string $history, string $message, string $language, array $evidence): string
    {
        $parts = [];
        if ($history !== '') {
            $parts[] = 'Conversation history (oldest to newest):';
            $parts[] = $history;
            $parts[] = '';
        }
        $parts[] = 'Authorized evidence (JSON from JPC Portal services/models; may be partial):';
        $parts[] = $evidence['payload'] ?? '{}';
        $parts[] = '';
        $parts[] = 'Admin question:';
        $parts[] = $message;
        $parts[] = '';
        $parts[] = 'Instructions: answer helpfully and naturally. Refer to sources in plain language. State missing/incomplete information clearly in a short section at the end when relevant. Do not fabricate. Do not predict future performance. Do not make employment decisions. Do not include tool class names, internal metadata, footers, or implementation details. Recommendations require human verification.';

        return implode("\n", $parts);
    }

    private function historyTranscript(AiChatConversation $conversation): string
    {
        $messages = AiChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_visible', true)
            ->whereIn('role', [AiMessageRole::User->value, AiMessageRole::Assistant->value, 'user', 'assistant'])
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $lines = [];
        $total = 0;
        foreach ($messages as $item) {
            $role = $item->role instanceof AiMessageRole ? $item->role->value : (string) $item->role;
            $label = $role === 'assistant' ? 'Assistant' : 'Admin';
            $content = trim(preg_replace('/\s+/', ' ', (string) $item->content) ?? (string) $item->content);
            $content = Str::limit($content, 900, '...');
            $line = $label.': '.$content;
            $total += strlen($line);
            if ($total > self::MAX_HISTORY_CHARS) {
                break;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function resolveConversation(User $user, ?int $conversationId, string $message): AiChatConversation
    {
        if ($conversationId) {
            $conversation = AiChatConversation::query()->find($conversationId);
            if ($conversation === null || (int) $conversation->user_id !== (int) $user->id) {
                throw new AuthorizationException('The selected conversation could not be found.');
            }
            if ($conversation->is_archived) {
                $conversation->forceFill(['is_archived' => false])->save();
            }
            $title = trim((string) $conversation->title);
            if (in_array(mb_strtolower($title), self::PLACEHOLDER_TITLES, true) && ! $conversation->messages()->exists()) {
                $conversation->forceFill(['title' => Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 60)])->save();
            }

            return $conversation;
        }

        return AiChatConversation::create([
            'user_id' => $user->id,
            'title' => Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?? $message) ?: 'New Chat', 60),
            'is_archived' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function response(AiChatConversation $conversation, AiChatMessage $assistantMessage, AiChatMessage $userMessage, bool $ok): array
    {
        return [
            'ok' => $ok,
            'conversation_id' => $conversation->id,
            'message' => $assistantMessage,
            'user_message' => $userMessage,
        ];
    }

    private function resolveLanguage(User $user, string $message, ?string $override = null): string
    {
        $candidate = strtolower(trim((string) $override));
        if (in_array($candidate, ['english', 'filipino', 'tagalog', 'taglish'], true)) {
            return $candidate;
        }
        $pref = strtolower(trim((string) ($user->ai_language_preference ?? 'auto')));
        if (in_array($pref, ['english', 'filipino', 'tagalog', 'taglish'], true)) {
            return $pref;
        }
        $t = mb_strtolower($message);
        $fil = preg_match('/\b(ang|ng|mga|sa|ako|hindi|paki|na|ko|mo|para|gawin|ulat|tulong)\b/u', $t);
        $eng = preg_match('/\b(the|and|for|with|please|report|tasks|summary|employee|progress)\b/u', $t);
        if ($fil && $eng) return 'taglish';
        if ($fil) return 'filipino';
        return 'english';
    }

    private function failureMessage(Throwable $e, string $language): string
    {
        $lang = $language === 'tagalog' ? 'filipino' : $language;

        if ($e instanceof AiProviderConfigurationException) {
            $base = $e->getMessage();
            return $lang === 'filipino'
                ? "Hindi pa naka-configure ang live AI provider para sa JPC AIDE. Nai-save ang iyong mensahe. {$base}"
                : ($lang === 'taglish'
                    ? "Hindi pa configured ang live AI provider para sa JPC AIDE. Na-save ang message mo. {$base}"
                    : "The live AI provider is not configured for JPC AIDE. Your message was saved. {$base}");
        }

        if ($e instanceof AiProviderException) {
            $msg = $e->getMessage();
            $detail = '';
            if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout') || str_contains($msg, 'cURL error 28')) {
                $detail = 'The AI service timed out.';
            } elseif (str_contains($msg, '429')) {
                $detail = 'The AI service is rate-limited. Please try again shortly.';
            } elseif (str_contains($msg, '401') || str_contains($msg, '403')) {
                $detail = 'The AI service rejected the request. Please verify the configured credentials and model.';
            } elseif (str_contains($msg, '404')) {
                $detail = 'The configured AI endpoint or model was not found. Please verify AI_ENDPOINT and AI_MODEL.';
            } elseif (str_contains($msg, 'empty')) {
                $detail = 'The AI service returned an empty response.';
            } else {
                $detail = 'The AI service is temporarily unavailable.';
            }

            return $lang === 'filipino'
                ? "{$detail} Nai-save ang iyong mensahe. Pakisubukang muli."
                : ($lang === 'taglish'
                    ? "{$detail} Na-save ang message mo. Subukan ulit."
                    : "{$detail} Your message was saved. Please try again.");
        }

        return $lang === 'filipino'
            ? 'Nagkaroon ng hindi inaasahang error ang JPC AIDE. Nai-save ang iyong mensahe.'
            : ($lang === 'taglish'
                ? 'Nagka-unexpected error ang JPC AIDE. Na-save ang message mo.'
                : 'JPC AIDE encountered an unexpected error. Your message was saved. Please try again.');
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return (string) $value;
    }

    private function safeMissingText(string $text): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $text) ?? $text), 180);
    }
}
