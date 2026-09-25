<?php

namespace App\Services\Performance;

use App\Models\AcademicYear;
use App\Models\Badge;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\EmployeePerformanceRecord;
use App\Models\MonthlyAchievement;
use App\Models\PerformancePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AchievementEvaluationService
{
    /**
     * Evaluate and persist monthly achievements and rankings for a given performance period.
     *
     * @return array{
     *   total_evaluated: int,
     *   employee_of_the_month: array<string, mixed>|null,
     *   rankings: list<array<string, mixed>>
     * }
     */
    public function evaluateMonthlyPeriod(PerformancePeriod $period): array
    {
        $year = $period->year;
        $month = $period->month ?? Carbon::parse($period->start_date)->month;

        $academicYear = AcademicYear::query()
            ->where('start_date', '<=', $period->end_date)
            ->where('end_date', '>=', $period->start_date)
            ->first() ?? AcademicYear::first();

        // Get all performance records for this period
        $records = EmployeePerformanceRecord::query()
            ->where('performance_period_id', $period->id)
            ->with(['employee.department'])
            ->get();

        if ($records->isEmpty()) {
            return [
                'total_evaluated' => 0,
                'employee_of_the_month' => null,
                'rankings' => [],
            ];
        }

        // Sort descending by productivity_score / overall_score, then completion_rate, then on_time_rate
        $sorted = $records->sortBy([
            ['productivity_score', 'desc'],
            ['completion_rate', 'desc'],
            ['on_time_rate', 'desc'],
        ])->values();

        $rankings = [];
        $employeeOfTheMonth = null;

        DB::transaction(function () use ($sorted, $period, $academicYear, $year, $month, &$rankings, &$employeeOfTheMonth) {
            foreach ($sorted as $index => $record) {
                $rank = $index + 1;
                $isEOM = ($rank === 1 && (float) $record->productivity_score > 0);

                if ($academicYear) {
                    $monthlyAchievement = MonthlyAchievement::updateOrCreate(
                        [
                            'employee_id' => $record->employee_id,
                            'academic_year_id' => $academicYear->id,
                            'month' => $month,
                            'year' => $year,
                        ],
                        [
                            'completion_rate' => $record->completion_rate,
                            'on_time_rate' => $record->on_time_rate,
                            'approved_reports' => $record->metadata['approved_reports'] ?? 0,
                            'completed_tasks' => $record->metadata['completed_tasks'] ?? 0,
                            'overall_score' => $record->productivity_score ?? $record->overall_score,
                            'rank' => $rank,
                            'is_employee_of_month' => $isEOM,
                        ]
                    );
                }

                // Award badges for this employee based on performance
                $this->evaluateAndAwardBadges($record, $period, $academicYear, $rank, $isEOM);

                $rankItem = [
                    'rank' => $rank,
                    'employee_id' => $record->employee_id,
                    'employee_name' => $record->employee?->full_name ?? 'Employee #' . $record->employee_id,
                    'department_id' => $record->employee?->department_id,
                    'department_name' => $record->employee?->department?->name ?? '—',
                    'productivity_score' => (float) $record->productivity_score,
                    'rating' => $record->rating?->value ?? (string) $record->rating,
                    'is_employee_of_month' => $isEOM,
                ];

                $rankings[] = $rankItem;
                if ($isEOM) {
                    $employeeOfTheMonth = $rankItem;
                }
            }
        });

        return [
            'total_evaluated' => count($rankings),
            'employee_of_the_month' => $employeeOfTheMonth,
            'rankings' => $rankings,
        ];
    }

    /**
     * Evaluate performance criteria and award eligible badges.
     */
    public function evaluateAndAwardBadges(
        EmployeePerformanceRecord $record,
        PerformancePeriod $period,
        ?AcademicYear $academicYear,
        int $rank,
        bool $isEmployeeOfMonth
    ): array {
        if (! $academicYear) {
            return [];
        }

        $awarded = [];
        $score = (float) $record->productivity_score;
        $onTime = (float) $record->on_time_rate;
        $completion = (float) $record->completion_rate;
        $completedTasks = (int) ($record->metadata['completed_tasks'] ?? 0);
        $approvedReports = (int) ($record->metadata['approved_reports'] ?? 0);

        // Badge 1: EMPLOYEE_OF_MONTH
        if ($isEmployeeOfMonth) {
            $this->grantBadge($record->employee_id, 'EMPLOYEE_OF_MONTH', $period, $academicYear, ['rank' => 1, 'score' => $score]);
            $awarded[] = 'EMPLOYEE_OF_MONTH';
        }

        // Badge 2: TOP_PERFORMER (Score >= 95)
        if ($score >= 95.00) {
            $this->grantBadge($record->employee_id, 'TOP_PERFORMER', $period, $academicYear, ['score' => $score]);
            $awarded[] = 'TOP_PERFORMER';
        }

        // Badge 3: ON_TIME_HERO (On-Time == 100%)
        if ($onTime >= 100.00 && ($record->metadata['submitted_reports'] ?? 0) > 0) {
            $this->grantBadge($record->employee_id, 'ON_TIME_HERO', $period, $academicYear, ['on_time_rate' => $onTime]);
            $awarded[] = 'ON_TIME_HERO';
        }

        // Badge 4: PERFECT_SUBMISSION (Completion 100% & On-Time 100%)
        if ($completion >= 100.00 && $onTime >= 100.00 && $completedTasks > 0) {
            $this->grantBadge($record->employee_id, 'PERFECT_SUBMISSION', $period, $academicYear, ['completion_rate' => $completion]);
            $awarded[] = 'PERFECT_SUBMISSION';
        }

        // Badge 5: TASK_CHAMPION (completed >= 10 tasks)
        if ($completedTasks >= 10) {
            $this->grantBadge($record->employee_id, 'TASK_CHAMPION', $period, $academicYear, ['completed_tasks' => $completedTasks]);
            $awarded[] = 'TASK_CHAMPION';
        }

        // Badge 6: REPORT_EXCELLENCE (approved reports >= 5)
        if ($approvedReports >= 5) {
            $this->grantBadge($record->employee_id, 'REPORT_EXCELLENCE', $period, $academicYear, ['approved_reports' => $approvedReports]);
            $awarded[] = 'REPORT_EXCELLENCE';
        }

        // Badge 7: OUTSTANDING (Score >= 90)
        if ($score >= 90.00) {
            $this->grantBadge($record->employee_id, 'OUTSTANDING', $period, $academicYear, ['score' => $score]);
            $awarded[] = 'OUTSTANDING';
        }

        // Badge 8: MOST_IMPROVED (Check improvement vs prior month)
        $improvement = $this->calculateImprovementDelta($record->employee_id, $period);
        if ($improvement['delta'] >= config('performance.achievements.most_improved_min_delta', 10.00)) {
            $this->grantBadge($record->employee_id, 'MOST_IMPROVED', $period, $academicYear, [
                'previous_score' => $improvement['previous_score'],
                'current_score' => $score,
                'delta' => $improvement['delta'],
            ]);
            $awarded[] = 'MOST_IMPROVED';
        }

        return $awarded;
    }

    /**
     * Compute score improvement compared to the preceding performance period.
     *
     * @return array{previous_score: float, delta: float}
     */
    public function calculateImprovementDelta(int $employeeId, PerformancePeriod $currentPeriod): array
    {
        $currentRecord = EmployeePerformanceRecord::where('employee_id', $employeeId)
            ->where('performance_period_id', $currentPeriod->id)
            ->first();

        $currentScore = $currentRecord ? (float) $currentRecord->productivity_score : 0.0;

        // Find immediately preceding period
        $previousPeriod = PerformancePeriod::where('period_type', $currentPeriod->period_type)
            ->where('end_date', '<', $currentPeriod->start_date)
            ->orderByDesc('end_date')
            ->first();

        if (! $previousPeriod) {
            return ['previous_score' => 0.0, 'delta' => 0.0];
        }

        $prevRecord = EmployeePerformanceRecord::where('employee_id', $employeeId)
            ->where('performance_period_id', $previousPeriod->id)
            ->first();

        if (! $prevRecord || (float) $prevRecord->productivity_score <= 0.0) {
            return ['previous_score' => 0.0, 'delta' => 0.0];
        }

        $prevScore = (float) $prevRecord->productivity_score;
        $delta = round($currentScore - $prevScore, 2);

        return [
            'previous_score' => $prevScore,
            'delta' => $delta,
        ];
    }

    /**
     * Idempotently grant a badge to an employee without duplicate creation.
     */
    public function grantBadge(
        int $employeeId,
        string $badgeCode,
        PerformancePeriod $period,
        AcademicYear $academicYear,
        array $criteriaSnapshot = []
    ): ?EmployeeBadge {
        $badge = Badge::firstOrCreate(
            ['code' => $badgeCode],
            [
                'name' => ucwords(str_replace('_', ' ', strtolower($badgeCode))),
                'description' => 'Awarded for performance excellence in ' . $period->name,
                'is_active' => true,
            ]
        );

        return EmployeeBadge::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'badge_id' => $badge->id,
                'academic_year_id' => $academicYear->id,
                'period_start' => $period->start_date,
                'period_end' => $period->end_date,
            ],
            [
                'awarded_at' => now(),
                'criteria_snapshot' => $criteriaSnapshot,
            ]
        );
    }
}
