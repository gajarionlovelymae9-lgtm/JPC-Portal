<?php

namespace App\Services\AI;

use App\Enums\AiIntent;
use App\Enums\AiMessageRole;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Services\AI\Tools\AiToolInterface;
use App\Services\AI\Tools\RoleAwareAiToolInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
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
     * Intent → ordered list of candidate tools. The first tool that exists and
     * supports the caller's role is used. Role-specific candidates share an
     * intent so "overdue tasks" answers institution-wide for an administrator
     * and with the employee's own records for an employee.
     *
     * @var array<string, list<string>>
     */
    private const INTENT_TOOL_MAP = [
        AiIntent::LATE_REPORTS->value => ['GetLateReportAnalyticsTool'],
        AiIntent::MONTHLY_ACHIEVEMENTS->value => ['GetMonthlyPerformanceReportTool'],
        AiIntent::EMPLOYEE_PERFORMANCE->value => ['GetEmployeeProgressReportTool'],
        AiIntent::DEPARTMENT_PERFORMANCE->value => ['GetDepartmentReportTool'],
        AiIntent::TOP_PERFORMERS->value => ['GetRankingTool'],
        AiIntent::PERFORMANCE_TREND->value => ['GetPerformanceTrendTool'],
        AiIntent::TARGET_PROGRESS->value => ['GetTargetAnalyticsTool'],
        AiIntent::TARGET_ACHIEVEMENT->value => ['GetTargetAnalyticsTool'],
        AiIntent::MANAGEMENT_SUMMARY->value => ['GetManagementSummaryTool'],
        AiIntent::DATA_QUALITY_STATISTICS->value => ['GetDataQualityStatisticsTool'],
        AiIntent::DATA_QUALITY_TRENDS->value => ['GetDataQualityTrendsTool'],
        AiIntent::CRITICAL_DATA_QUALITY_ISSUES->value => ['GetCriticalDataQualityIssuesTool'],
        AiIntent::DATA_QUALITY_ISSUES->value => ['GetDataQualityIssuesTool'],

        // Task monitoring — administrators first, employee-scoped fallback.
        AiIntent::TASK_STATISTICS->value => ['GetTaskMonitoringSummaryTool', 'GetMyTaskSummaryTool'],
        AiIntent::TASK_COMPLETION_RATE->value => ['GetTaskMonitoringSummaryTool', 'GetMyTaskSummaryTool'],
        AiIntent::OVERDUE_TASKS->value => ['GetOverdueTasksTool', 'GetMyOverdueTasksTool'],
        AiIntent::EMPLOYEE_TASKS->value => ['GetMyTaskSummaryTool'],
        AiIntent::UPCOMING_DEADLINES->value => ['GetMyUpcomingDeadlinesTool'],
        AiIntent::TASK_PRIORITIES->value => ['GetMyTaskPrioritiesTool'],
        AiIntent::TASK_EXPLANATION->value => ['ExplainMyTaskTool'],
    ];

    public function __construct(
        private readonly AiToolRegistry $toolRegistry,
        private readonly AiIntentDetector $intentDetector,
    ) {}

    public function handleMessage(User $user, ?int $conversationId, string $message, ?string $languagePreference = null): array
    {
        $language = $this->resolveLanguage($user, $message, $languagePreference);
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

        $tool = $this->resolveTool($intent, $user);

        if ($tool === null) {
            return $this->respond(
                $conversation,
                $intent,
                $this->helpMessage($user, $language),
                ['matched_tool' => null, 'hint' => 'No reporting tool matched the request.', 'language' => $language],
            );
        }

        // Authorized? The tool carries its own permission; never execute
        // without it, even if intent detection matched.
        $permission = $tool->requiredPermission();
        if ($permission !== null && ! $user->hasPermission($permission)) {
            return $this->respond(
                $conversation,
                $intent,
                $this->permissionDeniedMessage($tool, $permission, $language),
                ['matched_tool' => $tool->name(), 'error' => 'permission_denied', 'language' => $language],
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
                    'language' => $language,
                ],
            );
        } catch (AuthorizationException) {
            return $this->respond(
                $conversation,
                $intent,
                $this->forbiddenMessage($language),
                ['matched_tool' => $tool->name(), 'error' => 'forbidden', 'language' => $language],
            );
        } catch (\Throwable $e) {
            return $this->respond(
                $conversation,
                $intent,
                $this->errorMessage($language),
                ['matched_tool' => $tool->name(), 'error' => $e->getMessage(), 'language' => $language],
            );
        }
    }

    /* ---------------------------------------------------------------------
     | Internals
     * ------------------------------------------------------------------- */

    /**
     * Resolve the first candidate tool that exists and supports the caller.
     *
     * Role-aware tools (RoleAwareAiToolInterface) declare which callers they
     * serve. That is what lets one intent — e.g. "overdue tasks" — return the
     * institution-wide monitoring tool for an administrator and the
     * employee-scoped tool for an employee. Tools without the interface keep
     * their existing behaviour and are still permission-checked by the caller.
     */
    private function resolveTool(string $intent, User $user): ?AiToolInterface
    {
        $toolNames = self::INTENT_TOOL_MAP[$intent] ?? [];

        foreach ($toolNames as $toolName) {
            $tool = $this->toolRegistry->find($toolName);

            if ($tool === null) {
                continue;
            }

            if ($tool instanceof RoleAwareAiToolInterface && ! $tool->supports($user)) {
                continue;
            }

            return $tool;
        }

        return null;
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

        if (in_array($toolName, ['GetMyTaskPrioritiesTool'], true)) {
            $params['limit'] = $this->intentDetector->extractLimit($message, 5);
        }

        if (in_array($toolName, ['ExplainMyTaskTool'], true)) {
            $params['query'] = $message;
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
        // Task tools compose their own grounded, human-readable answer from the
        // real records. Prefer it over the generic metric read-out so the user
        // gets a direct reply instead of a field dump.
        if (isset($result['answer']) && is_string($result['answer']) && trim($result['answer']) !== '') {
            return $result['answer'];
        }

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

    private function helpMessage(User $user, string $language = 'english'): string
    {
        $tools = array_filter(
            $this->toolRegistry->all(),
            function (AiToolInterface $tool) use ($user): bool {
                if ($tool instanceof RoleAwareAiToolInterface && ! $tool->supports($user)) {
                    return false;
                }

                $permission = $tool->requiredPermission();

                return $permission === null || $user->hasPermission($permission);
            },
        );

        $lines = array_map(
            fn (AiToolInterface $t) => '- '.$t->description(),
            $tools,
        );

        $intro = match ($this->effectiveLanguage($language)) {
            'filipino' => 'Makakasagot ako ng mga tanong tungkol sa performance gamit ang iyong live na mga report at awtorisadong data. Maaari mo akong tanungin ng mga tulad ng:',
            'taglish' => 'Makakasagot ako ng performance questions gamit ang live reports at authorized data mo. Puwede mo akong tanungin ng mga tulad ng:',
            default => 'I can answer performance questions using your live reports. Ask me things like:',
        };

        return $intro."\n".implode("\n", $lines);
    }

    private function permissionDeniedMessage(AiToolInterface $tool, string $permission, string $language): string
    {
        return match ($this->effectiveLanguage($language)) {
            'filipino' => "Wala kang pahintulot na patakbuhin ang {$tool->description()} report. Kailangan nito ang \"{$permission}\" permission na wala sa iyong account.",
            'taglish' => "Wala kang permission na i-run ang {$tool->description()} report. Kailangan nito ang \"{$permission}\" permission na hindi kasama sa account mo.",
            default => "You do not have permission to run the {$tool->description()} report. This requires the \"{$permission}\" permission, which your account does not currently hold.",
        };
    }

    private function forbiddenMessage(string $language): string
    {
        return match ($this->effectiveLanguage($language)) {
            'filipino' => 'Wala sa iyong awtorisadong saklaw ang report na ito, kaya hindi ito maaaring gawin para sa iyo.',
            'taglish' => 'Outside ng authorized scope mo ang report na ito, kaya hindi ito puwedeng i-generate para sa iyo.',
            default => 'That report is outside your allowed scope, so it cannot be generated for you.',
        };
    }

    private function errorMessage(string $language): string
    {
        return match ($this->effectiveLanguage($language)) {
            'filipino' => 'Nagkaroon ng error ang report tool. Pakilinaw ang iyong mga filter at subukang muli.',
            'taglish' => 'Nagka-error ang report tool. Paki-narrow ang filters mo at subukan ulit.',
            default => 'The report tool ran into an error. Please narrow your filters and try again.',
        };
    }

    private function resolveLanguage(User $user, string $message, ?string $override = null): string
    {
        $candidate = $this->normalizeLanguage($override);
        if ($candidate !== 'auto') {
            return $candidate;
        }

        $userPref = $this->normalizeLanguage($user->ai_language_preference ?? null);
        if ($userPref !== 'auto') {
            return $userPref;
        }

        return $this->detectLanguage($message);
    }

    private function normalizeLanguage(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if ($value === 'tagalog') {
            $value = 'filipino';
        }

        return in_array($value, ['auto', 'english', 'filipino', 'taglish'], true) ? $value : 'auto';
    }

    private function effectiveLanguage(string $language): string
    {
        $language = $this->normalizeLanguage($language);
        return $language === 'auto' ? 'english' : $language;
    }

    private function detectLanguage(string $message): string
    {
        $text = mb_strtolower($message);
        $filipino = 0;
        $english = 0;
        $padded = ' '.preg_replace('/\s+/u', ' ', $text).' ';

        foreach ([' ako ', ' ikaw ', ' kami ', ' tayo ', ' ang ', ' ng ', ' mga ', ' sa ', ' para ', ' hindi ', ' wala ', ' meron ', ' may ', ' po ', ' opo ', ' paki', ' pwede ', ' puwede ', ' kailangan ', ' gusto ', ' salamat ', ' ulat ', ' buod ', ' gawin ', ' natapos '] as $marker) {
            if (str_contains($padded, $marker)) {
                $filipino++;
            }
        }
        foreach ([' the ', ' and ', ' or ', ' but ', ' with ', ' for ', ' this ', ' that ', ' my ', ' your ', ' please ', ' report ', ' summary ', ' completed ', ' tasks ', ' show ', ' help '] as $marker) {
            if (str_contains($padded, $marker)) {
                $english++;
            }
        }

        if ($filipino >= 2 && $english >= 2) {
            return 'taglish';
        }
        if ($filipino > $english && $filipino >= 1) {
            return 'filipino';
        }

        return 'english';
    }

    /**
     * Persist the assistant message and return the Phase 7 envelope.
     *
     * @param  array<string, mixed>  $results
     * @return array{message: AiChatConversation|Model, intent: string, period: string, results: array, actions: list<mixed>}
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
        $keysToTrim = [
            'by_employee', 'by_department', 'department_rankings', 'targets',
            'reports', 'ranking', 'trend', 'pending_by_employee',
            'overdue_by_employee', 'departments', 'upcoming_deadlines',
            'overdue_tasks', 'tasks', 'priorities', 'deadlines', 'candidates',
        ];
        foreach ($keysToTrim as $key) {
            if (isset($result[$key]) && is_array($result[$key]) && count($result[$key]) > 10) {
                $result[$key] = array_slice($result[$key], 0, 10);
            }
        }

        return $result;
    }
}
