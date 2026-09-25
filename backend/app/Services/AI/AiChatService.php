<?php

namespace App\Services\AI;

use App\Enums\AiIntent;
use App\Enums\AiMessageRole;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Services\AI\Tools\AiToolInterface;
use Illuminate\Support\Str;

/**
 * AiChatService — grounded, permission-checked AI chat assistant (Phase 7 +
 * Phase 9 reporting tools).
 *
 * Pipeline per message:
 *   1. detect intent (deterministic keyword table — never a hallucinated
 *      guess),
 *   2. resolve the single matching tool from AiToolRegistry,
 *   3. permission check via the tool's requiredPermission(),
 *   4. execute against the real Reporting/Analytics services (role scoped),
 *   5. compose a grounded narrative from the tool's data.
 *
 * If no tool matches, the assistant replies with the list of things it can
 * actually do instead of inventing an answer.
 */
class AiChatService
{
    /**
     * Intent → tool name mapping. Extend when new report tools are added.
     *
     * @var array<string, string>
     */
    private const INTENT_TOOL_MAP = [
        AiIntent::LATE_REPORTS->value => 'GetLateReportAnalyticsTool',
        AiIntent::MONTHLY_ACHIEVEMENTS->value => 'GetMonthlyPerformanceReportTool',
        AiIntent::EMPLOYEE_PERFORMANCE->value => 'GetEmployeeProgressReportTool',
        AiIntent::DEPARTMENT_PERFORMANCE->value => 'GetDepartmentReportTool',
        AiIntent::TOP_PERFORMERS->value => 'GetRankingTool',
        AiIntent::PERFORMANCE_TREND->value => 'GetPerformanceTrendTool',
        AiIntent::TARGET_PROGRESS->value => 'GetTargetAnalyticsTool',
        AiIntent::TARGET_ACHIEVEMENT->value => 'GetTargetAnalyticsTool',
        AiIntent::MANAGEMENT_SUMMARY->value => 'GetManagementSummaryTool',
        AiIntent::DATA_QUALITY_STATISTICS->value => 'GetDataQualityStatisticsTool',
        AiIntent::DATA_QUALITY_TRENDS->value => 'GetDataQualityTrendsTool',
        AiIntent::CRITICAL_DATA_QUALITY_ISSUES->value => 'GetCriticalDataQualityIssuesTool',
        AiIntent::DATA_QUALITY_ISSUES->value => 'GetDataQualityIssuesTool',
    ];

    public function __construct(
        private readonly AiToolRegistry $toolRegistry,
        private readonly AiIntentDetector $intentDetector,
    ) {}

    public function handleMessage(User $user, ?int $conversationId, string $message): array
    {
        $conversation = $conversationId
            ? AiChatConversation::find($conversationId)
            : AiChatConversation::create([
                'user_id' => $user->id,
                'title' => Str::limit($message, 60),
            ]);

        if ($conversation === null) {
            $conversation = AiChatConversation::create([
                'user_id' => $user->id,
                'title' => Str::limit($message, 60),
            ]);
        }

        $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $message,
        ]);

        $detected = $this->intentDetector->detect($message);
        $intent = $detected['intent'];
        $filters = $detected['filters'] ?? [];

        $tool = $this->resolveTool($intent);

        if ($tool === null) {
            return $this->respond(
                $conversation,
                $intent,
                $this->helpMessage(),
                ['matched_tool' => null, 'hint' => 'No reporting tool matched the request.'],
            );
        }

        // Authorized? The tool carries its own permission; never execute
        // without it, even if intent detection matched.
        $permission = $tool->requiredPermission();
        if ($permission !== null && ! $user->hasPermission($permission)) {
            return $this->respond(
                $conversation,
                $intent,
                "You do not have permission to run the {$tool->description()} report. ".
                "This requires the \"{$permission}\" permission, which your account does not currently hold.",
                ['matched_tool' => $tool->name(), 'error' => 'permission_denied'],
            );
        }

        try {
            $params = $this->buildParams($tool->name(), $filters, $message);
            $result = $tool->execute($params, $user);

            return $this->respond(
                $conversation,
                $intent,
                $this->narrate($tool, $result),
                [
                    'matched_tool' => $tool->name(),
                    'tool_result' => $this->slim($result),
                ],
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return $this->respond(
                $conversation,
                $intent,
                'That report is outside your allowed scope, so it cannot be generated for you.',
                ['matched_tool' => $tool->name(), 'error' => 'forbidden'],
            );
        } catch (\Throwable $e) {
            return $this->respond(
                $conversation,
                $intent,
                'The report tool ran into an error. Please narrow your filters and try again.',
                ['matched_tool' => $tool->name(), 'error' => $e->getMessage()],
            );
        }
    }

    /* ---------------------------------------------------------------------
     | Internals
     * ------------------------------------------------------------------- */

    private function resolveTool(string $intent): ?AiToolInterface
    {
        $toolName = self::INTENT_TOOL_MAP[$intent] ?? null;
        if ($toolName === null) {
            return null;
        }

        return $this->toolRegistry->find($toolName);
    }

    /**
     * Merge the detected period filters with per-tool params.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildParams(string $toolName, array $filters, string $message): array
    {
        $params = [
            'year' => $filters['year'] ?? now()->year,
            'month' => $filters['month'] ?? now()->month,
        ];

        if (in_array($toolName, ['GetRankingTool'], true)) {
            $params['limit'] = $this->intentDetector->extractLimit($message);
        }

        if (in_array($toolName, ['GetPerformanceTrendTool'], true)) {
            $params['months'] = $filters['months'] ?? config('reports.trend_months', 6);
        }

        return $params;
    }

    /**
     * Deterministic, grounded read-out of a tool result — never free-form AI
     * prose. Each tool's known keys are echoed back with their labels.
     */
    private function narrate(AiToolInterface $tool, array $result): string
    {
        $label = $tool->name();

        $period = $result['period'] ?? ($result['period_label'] ?? now()->format('F Y'));

        $bits = [];

        if (isset($result['metrics']) && is_array($result['metrics'])) {
            $metrics = $this->flattenReadable($result['metrics']);
            if ($metrics !== []) {
                $bits[] = 'Key figures: '.implode(', ', $metrics).'.';
            }
        }

        if (isset($result['summary']) && is_array($result['summary']) && $bits === []) {
            $summary = $this->flattenReadable($result['summary']);
            if ($summary !== []) {
                $bits[] = 'Summary: '.implode(', ', $summary).'.';
            }
        }

        if (isset($result['top_performer']['name'])) {
            $bits[] = 'Top performer: '.$result['top_performer']['name'].' ('.($result['top_performer']['score'] ?? 'N/A').').';
        }

        if (isset($result['top_department']['department_name'])) {
            $bits[] = 'Top department: '.$result['top_department']['department_name'].'.';
        }

        if (isset($result['delta']['productivity_diff'])) {
            $bits[] = 'Productivity delta vs previous period: '.$result['delta']['productivity_diff'].'.';
        }

        if (isset($result['narrative'])) {
            $bits[] = $result['narrative'];
        }

        if ($bits === []) {
            $bits[] = 'No detailed figures were returned for this period.';
        }

        return Str::headline($label).' — '.$period.'. '.implode(' ', array_unique($bits));
    }

    /**
     * Collapse a metrics/summary array into "Label: value" strings.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function flattenReadable(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_array($value) || $value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            }

            $label = ucwords(str_replace('_', ' ', (string) $key));

            $out[] = $label.': '.$value;
        }

        return $out;
    }

    private function helpMessage(): string
    {
        $tools = $this->toolRegistry->all();

        $lines = array_map(
            fn (AiToolInterface $t) => '- '.$t->name().': '.$t->description(),
            $tools,
        );

        return "I can answer performance questions using your live reports. Ask me things like:\n".implode("\n", $lines);
    }

    /**
     * Persist the assistant message and return the Phase 7 envelope.
     *
     * @param  array<string, mixed>  $results
     * @return array{message: AiChatConversation|\Illuminate\Database\Eloquent\Model, intent: string, period: string, results: array, actions: list<mixed>}
     */
    private function respond(AiChatConversation $conversation, string $intent, string $content, array $results): array
    {
        $assistantMsg = $conversation->messages()->create([
            'role' => AiMessageRole::Assistant,
            'content' => $content,
            'intent' => $intent,
            'tool_results' => $results,
        ]);

        return [
            'message' => $assistantMsg,
            'intent' => $intent,
            'period' => now()->format('Y-m'),
            'results' => $results,
            'actions' => [],
        ];
    }

    /**
     * Keep persisted tool_results compact (drop large nested report lists).
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function slim(array $result): array
    {
        $keysToTrim = ['by_employee', 'by_department', 'department_rankings', 'targets', 'reports', 'ranking', 'trend'];
        foreach ($keysToTrim as $key) {
            if (isset($result[$key]) && is_array($result[$key]) && count($result[$key]) > 10) {
                $result[$key] = array_slice($result[$key], 0, 10);
            }
        }

        return $result;
    }
}