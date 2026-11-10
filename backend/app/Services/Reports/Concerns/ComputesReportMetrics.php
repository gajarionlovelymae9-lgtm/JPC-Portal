<?php

namespace App\Services\Reports\Concerns;

/**
 * Shared rate/metric helpers for the report services.
 *
 * Every percentage exposed by the reports module is computed here so the
 * formulas stay identical across Task Completion, Employee Performance and
 * Department Performance reports.
 */
trait ComputesReportMetrics
{
    /**
     * Percentage of `$part` within `$whole`, rounded to two decimals.
     * Returns 0.0 when the whole is empty (avoiding division by zero).
     */
    protected function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
    }

    /**
     * Split a set of completed assignments/tasks into on-time and late.
     *
     * Mirrors the dashboard's SQL rule exactly (`completed_at > due_date`
     * means late) so reports never disagree with the management dashboard.
     * A record completed on its due day counts as on time.
     *
     * @param  iterable<object>  $completed  records exposing completed_at/due_date
     * @return array{on_time: int, late: int}
     */
    protected function onTimeSplit(iterable $completed): array
    {
        $onTime = 0;
        $late = 0;

        foreach ($completed as $record) {
            $completedAt = $record->completed_at;
            $dueDate = $record->due_date;

            if ($completedAt === null) {
                continue;
            }

            $isLate = $dueDate !== null && $completedAt->gt($dueDate);

            if ($isLate) {
                $late++;
            } else {
                $onTime++;
            }
        }

        return ['on_time' => $onTime, 'late' => $late];
    }
}
