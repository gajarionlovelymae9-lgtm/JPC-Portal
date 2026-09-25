<?php

namespace App\Services\AI;

use App\Enums\AiIntent;
use Carbon\Carbon;

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
        'management summary' => [AiIntent::MANAGEMENT_SUMMARY],
        'executive summary' => [AiIntent::MANAGEMENT_SUMMARY],
        'data quality' => [AiIntent::DATA_QUALITY_STATISTICS, AiIntent::DATA_QUALITY_TRENDS, AiIntent::CRITICAL_DATA_QUALITY_ISSUES, AiIntent::DATA_QUALITY_ISSUES],
        'critical data' => [AiIntent::CRITICAL_DATA_QUALITY_ISSUES],
        'late submission' => [AiIntent::LATE_REPORTS],
        'late report' => [AiIntent::LATE_REPORTS],
        'late' => [AiIntent::LATE_REPORTS],
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

    public function extractLimit(string $message): int
    {
        if (preg_match('/top\s+(\d+)/i', $message, $match)) {
            return (int) $match[1];
        }

        return config('reports.top_performers_limit', 5);
    }
}