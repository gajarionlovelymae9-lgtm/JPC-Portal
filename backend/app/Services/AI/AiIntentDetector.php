<?php

namespace App\Services\AI;

use App\Enums\AiIntent;

/**
 * AiIntentDetector — deterministic, keyword-based intent detection for the
 * AI chat assistant (Phase 7 / Phase 9 reporting tools).
 *
 * Grounded by design: we never guess — the detected intent is mapped from an
 * explicit keyword table and the resulting tool call is permission-checked
 * before execution, so responses always come from real report services.
 */
class AiIntentDetector
{
    /**
     * Keyword → intent map. Order matters: longer, more specific phrases are
     * checked first.
     *
     * @var array<string, array<int, AiIntent>>
     */
    private const KEYWORDS = [
        // Task questions. Task-specific phrases are checked before the generic
        // report/pending/summary words so "pending tasks" is not mistaken for
        // "pending reports" and "my work progress" is not mistaken for the
        // reports-based employee progress report.
        'who has overdue' => [AiIntent::OVERDUE_TASKS],
        'overdue task' => [AiIntent::OVERDUE_TASKS],
        'tasks are overdue' => [AiIntent::OVERDUE_TASKS],
        'overdue' => [AiIntent::OVERDUE_TASKS],
        'late task' => [AiIntent::OVERDUE_TASKS],
        'approaching' => [AiIntent::UPCOMING_DEADLINES],
        'upcoming deadline' => [AiIntent::UPCOMING_DEADLINES],
        'due soon' => [AiIntent::UPCOMING_DEADLINES],
        'coming up' => [AiIntent::UPCOMING_DEADLINES],
        'prioritize' => [AiIntent::TASK_PRIORITIES],
        'prioritise' => [AiIntent::TASK_PRIORITIES],
        'priority task' => [AiIntent::TASK_PRIORITIES],
        'what should i work on' => [AiIntent::TASK_PRIORITIES],
        'explain assigned' => [AiIntent::TASK_EXPLANATION],
        'explain task' => [AiIntent::TASK_EXPLANATION],
        'explain my' => [AiIntent::TASK_EXPLANATION],
        'describe task' => [AiIntent::TASK_EXPLANATION],
        'tell me about task' => [AiIntent::TASK_EXPLANATION],
        'my task progress' => [AiIntent::TASK_STATISTICS],
        'my work progress' => [AiIntent::TASK_STATISTICS],
        'work progress' => [AiIntent::TASK_STATISTICS],
        'task progress' => [AiIntent::TASK_STATISTICS],
        'task summary' => [AiIntent::TASK_STATISTICS],
        'task statistics' => [AiIntent::TASK_STATISTICS],
        'task monitoring' => [AiIntent::TASK_STATISTICS],
        'task completion rate' => [AiIntent::TASK_COMPLETION_RATE],
        'task completion' => [AiIntent::TASK_COMPLETION_RATE],
        'task report' => [AiIntent::TASK_STATISTICS],
        'pending task' => [AiIntent::TASK_STATISTICS],
        'completed task' => [AiIntent::TASK_STATISTICS],
        'completed and pending' => [AiIntent::TASK_STATISTICS],
        'completed and overdue' => [AiIntent::TASK_STATISTICS],
        'how many tasks' => [AiIntent::TASK_STATISTICS],
        'most pending' => [AiIntent::TASK_STATISTICS],
        'summarize employee progress' => [AiIntent::TASK_STATISTICS],
        'summarise employee progress' => [AiIntent::TASK_STATISTICS],
        'summary of employee progress' => [AiIntent::TASK_STATISTICS],
        'employee progress reports' => [AiIntent::TASK_STATISTICS],
        'employee task progress' => [AiIntent::TASK_STATISTICS],
        'department task' => [AiIntent::TASK_STATISTICS],
        'department progress' => [AiIntent::TASK_STATISTICS],
        'performance insight' => [AiIntent::MANAGEMENT_SUMMARY],
        'my tasks' => [AiIntent::EMPLOYEE_TASKS],
        'my task' => [AiIntent::EMPLOYEE_TASKS],
        'assigned task' => [AiIntent::EMPLOYEE_TASKS],
        'my assignments' => [AiIntent::EMPLOYEE_TASKS],

        'management summary' => [AiIntent::MANAGEMENT_SUMMARY],
        'executive summary' => [AiIntent::MANAGEMENT_SUMMARY],
        'data quality' => [AiIntent::DATA_QUALITY_STATISTICS, AiIntent::DATA_QUALITY_TRENDS, AiIntent::CRITICAL_DATA_QUALITY_ISSUES, AiIntent::DATA_QUALITY_ISSUES],
        'critical data' => [AiIntent::CRITICAL_DATA_QUALITY_ISSUES],
        'summarize report' => [AiIntent::REPORT_AI_SUMMARY],
        'summarise report' => [AiIntent::REPORT_AI_SUMMARY],
        'report summary' => [AiIntent::REPORT_AI_SUMMARY],
        'summary of report' => [AiIntent::REPORT_AI_SUMMARY],
        'ai findings' => [AiIntent::AI_FINDINGS],
        'reference concern' => [AiIntent::REFERENCE_CONCERNS],
        'similarity concern' => [AiIntent::SIMILARITY_CONCERNS],
        'late submission' => [AiIntent::LATE_REPORTS],
        'late report' => [AiIntent::LATE_REPORTS],
        'late' => [AiIntent::LATE_REPORTS],
        'pending report' => [AiIntent::PENDING_REPORTS],
        'pending' => [AiIntent::PENDING_REPORTS],
        'target achievement' => [AiIntent::TARGET_ACHIEVEMENT],
        'target progress' => [AiIntent::TARGET_PROGRESS],
        'target' => [AiIntent::TARGET_PROGRESS],
        'department performance' => [AiIntent::DEPARTMENT_PERFORMANCE],
        'department report' => [AiIntent::DEPARTMENT_PERFORMANCE],
        'department' => [AiIntent::DEPARTMENT_PERFORMANCE],
        'employee performance' => [AiIntent::EMPLOYEE_PERFORMANCE],
        'employee progress' => [AiIntent::EMPLOYEE_PERFORMANCE],
        'my progress' => [AiIntent::EMPLOYEE_PERFORMANCE],
        'progress report' => [AiIntent::EMPLOYEE_PERFORMANCE],
        'employee report' => [AiIntent::EMPLOYEE_PERFORMANCE],
        'top performer' => [AiIntent::TOP_PERFORMERS],
        'leaderboard' => [AiIntent::TOP_PERFORMERS],
        'ranking' => [AiIntent::TOP_PERFORMERS],
        'top 5' => [AiIntent::TOP_PERFORMERS],
        'top ten' => [AiIntent::TOP_PERFORMERS],
        'performance trend' => [AiIntent::PERFORMANCE_TREND],
        'trend' => [AiIntent::PERFORMANCE_TREND],
        'monthly report' => [AiIntent::MONTHLY_ACHIEVEMENTS],
        'monthly performance' => [AiIntent::MONTHLY_ACHIEVEMENTS],
        'overview' => [AiIntent::DATA_QUALITY_STATISTICS],
        'summary' => [AiIntent::DATA_QUALITY_STATISTICS],
    ];

    /**
     * Detect the primary intent for a message.
     *
     * @return array{intent: string, filters: array<string, mixed>}
     */
    public function detect(string $message): array
    {
        $normalized = strtolower($message);
        $filters = [
            'year' => now()->year,
            'month' => now()->month,
        ];

        foreach (self::KEYWORDS as $phrase => $intents) {
            if (! str_contains($normalized, $phrase)) {
                continue;
            }

            $filters = array_merge($filters, $this->extractDateRange($message));

            return ['intent' => $intents[0]->value, 'filters' => $filters];
        }

        return ['intent' => 'DEFAULT', 'filters' => $filters];
    }

    /**
     * Pull an explicit period out of the free text (e.g. "January 2025",
     * "for March", "last 3 months", "2025").
     *
     * @return array<string, int>
     */
    public function extractDateRange(string $message): array
    {
        $normalized = strtolower($message);

        if (preg_match('/(?<!\d)(20\d{2})(?!\d)/', $normalized, $yearMatch)) {
            $year = (int) $yearMatch[1];
        } else {
            $year = now()->year;
        }

        $months = [
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
            'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        foreach ($months as $name => $month) {
            if (str_contains($normalized, $name)) {
                return ['year' => $year, 'month' => $month];
            }
        }

        if (preg_match('/last\s+(\d+)\s+months?/', $normalized, $rangeMatch)) {
            $limit = (int) $rangeMatch[1];

            return ['year' => $year, 'month' => now()->month, 'months' => $limit];
        }

        return ['year' => $year, 'month' => now()->month];
    }

    public function extractLimit(string $message, ?int $default = null): int
    {
        if (preg_match('/top\s+(\d+)/i', $message, $match)) {
            return (int) $match[1];
        }

        return $default ?? (int) config('reports.top_performers_limit', 5);
    }
}
