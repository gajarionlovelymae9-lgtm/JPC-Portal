<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\Report;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class KpiCalculationService
{
    /**
     * Calculate Task Completion Rate.
     * Formula: Completed Tasks / Assigned Tasks * 100
     *
     * @return array{rate: float, completed: int, total: int}
     */
    public function calculateTaskCompletionRate(Employee $employee, Carbon $start, Carbon $end): array
    {
        $assignments = TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('assigned_at', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end])
                    ->orWhereBetween('due_date', [$start, $end])
                    ->orWhereHas('task', function (Builder $tq) use ($start, $end) {
                        $tq->whereBetween('due_date', [$start, $end]);
                    });
            })
            ->where('status', '!=', 'cancelled')
            ->get();

        $total = $assignments->count();
        if ($total === 0) {
            // Check fallback for any active assignments in that month
            $assignments = TaskAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('status', '!=', 'cancelled')
                ->where('created_at', '<=', $end)
                ->get();
            $total = $assignments->count();
        }

        $completed = $assignments->filter(function ($assignment) {
            return $assignment->status === 'completed' || (int) $assignment->progress_percentage === 100;
        })->count();

        $rate = $total > 0 ? round(($completed / $total) * 100, 2) : 0.00;

        return [
            'rate' => min(100.00, max(0.00, $rate)),
            'completed' => $completed,
            'total' => $total,
        ];
    }

    /**
     * Calculate Report Completion Rate.
     * Formula: Completed Reports / Expected Reports * 100
     *
     * @return array{rate: float, completed: int, expected: int}
     */
    public function calculateReportCompletionRate(Employee $employee, Carbon $start, Carbon $end, int $expectedFallback = 0): array
    {
        // Expected reports are based on assigned tasks in period that require reports
        $taskCount = TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('assigned_at', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end]);
            })
            ->count();

        $expected = $taskCount > 0 ? $taskCount : $expectedFallback;

        // Submitted reports in period (excluding drafts and cancelled)
        $reports = Report::query()
            ->where('employee_id', $employee->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('submitted_at', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end]);
            })
            ->get();

        $completed = $reports->count();

        // If expected is 0 but we have reports submitted, expected is at least completed
        if ($expected === 0 && $completed > 0) {
            $expected = $completed;
        }

        $rate = $expected > 0 ? round(($completed / $expected) * 100, 2) : 0.00;

        return [
            'rate' => min(100.00, max(0.00, $rate)),
            'completed' => $completed,
            'expected' => $expected,
        ];
    }

    /**
     * Calculate On-Time Submission Rate.
     * Formula: On-Time Reports / Submitted Reports * 100
     *
     * @return array{rate: float, on_time: int, submitted: int, late: int}
     */
    public function calculateOnTimeRate(Employee $employee, Carbon $start, Carbon $end): array
    {
        $reports = Report::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$start, $end])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get();

        $submitted = $reports->count();
        if ($submitted === 0) {
            return [
                'rate' => 0.00,
                'on_time' => 0,
                'submitted' => 0,
                'late' => 0,
            ];
        }

        $onTime = $reports->filter(function ($report) {
            return ! $report->is_late;
        })->count();

        $late = $submitted - $onTime;
        $rate = round(($onTime / $submitted) * 100, 2);

        return [
            'rate' => min(100.00, max(0.00, $rate)),
            'on_time' => $onTime,
            'submitted' => $submitted,
            'late' => $late,
        ];
    }

    /**
     * Calculate Approval Rate.
     * Formula: Approved Reports / Reviewed Reports * 100
     *
     * @return array{rate: float, approved: int, reviewed: int, rejected: int}
     */
    public function calculateApprovalRate(Employee $employee, Carbon $start, Carbon $end): array
    {
        $reports = Report::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('approved_at', [$start, $end])
                    ->orWhereBetween('rejected_at', [$start, $end])
                    ->orWhereBetween('updated_at', [$start, $end]);
            })
            ->get();

        $reviewed = $reports->count();
        if ($reviewed === 0) {
            return [
                'rate' => 0.00,
                'approved' => 0,
                'reviewed' => 0,
                'rejected' => 0,
            ];
        }

        $approved = $reports->filter(function ($r) {
            return $r->status === 'approved'
                || (is_object($r->status) && property_exists($r->status, 'value') && $r->status->value === 'approved')
                || (is_object($r->status) && method_exists($r->status, 'name') && $r->status->name === 'Approved');
        })->count();
        $rejected = $reviewed - $approved;
        $rate = round(($approved / $reviewed) * 100, 2);

        return [
            'rate' => min(100.00, max(0.00, $rate)),
            'approved' => $approved,
            'reviewed' => $reviewed,
            'rejected' => $rejected,
        ];
    }

    /**
     * Calculate Target Achievement Rate.
     * Formula: Achieved Target / Assigned Target * 100
     *
     * @return array{rate: float, achieved: float, target: float}
     */
    public function calculateTargetAchievementRate(Employee $employee, Carbon $start, Carbon $end): array
    {
        $targets = EmployeeTarget::query()
            ->where('employee_id', $employee->id)
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhere(function ($sub) use ($start, $end) {
                        $sub->whereNotNull('start_date')
                            ->where('start_date', '<=', $end->toDateString())
                            ->where(function ($sub2) use ($start) {
                                $sub2->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $start->toDateString());
                            });
                    })
                    ->orWhereHas('annualPlan.academicYear', function (Builder $ay) use ($start, $end) {
                        $ay->where('start_date', '<=', $end->toDateString())
                            ->where('end_date', '>=', $start->toDateString());
                    });
            })
            ->get();

        if ($targets->isEmpty()) {
            return [
                'rate' => 0.00,
                'achieved' => 0.00,
                'target' => 0.00,
            ];
        }

        $totalTarget = 0.00;
        $totalAchieved = 0.00;

        foreach ($targets as $target) {
            $targetVal = (float) ($target->target_value ?? 100.00);

            if (isset($target->achieved_value) && $target->achieved_value !== null) {
                $achievedVal = (float) $target->achieved_value;
            } elseif (in_array(strtolower((string) $target->status), ['achieved', 'completed', 'approved'], true)) {
                $achievedVal = $targetVal;
            } else {
                $achievedVal = 0.00;
            }

            $totalTarget += $targetVal;
            $totalAchieved += $achievedVal;
        }

        $rate = $totalTarget > 0 ? round(($totalAchieved / $totalTarget) * 100, 2) : 0.00;

        return [
            'rate' => min(100.00, max(0.00, $rate)),
            'achieved' => round($totalAchieved, 2),
            'target' => round($totalTarget, 2),
        ];
    }
}
