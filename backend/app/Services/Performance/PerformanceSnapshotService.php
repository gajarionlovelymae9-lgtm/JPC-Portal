<?php

namespace App\Services\Performance;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeProgressSnapshot;
use App\Models\PerformancePeriod;
use App\Models\PerformanceScoreBreakdown;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PerformanceSnapshotService
{
    public function __construct(
        protected KpiCalculationService $kpiService,
        protected ProductivityScoreService $scoreService,
        protected AchievementEvaluationService $achievementService
    ) {}

    /**
     * Calculate and snapshot performance for a single employee in a period.
     */
    public function calculateEmployeePerformance(
        Employee $employee,
        PerformancePeriod $period,
        ?array $customWeights = null,
        ?User $actor = null,
        ?string $recalculationReason = null
    ): EmployeePerformanceRecord {
        $start = Carbon::parse($period->start_date)->startOfDay();
        $end = Carbon::parse($period->end_date)->endOfDay();

        $academicYear = AcademicYear::query()
            ->where('start_date', '<=', $period->end_date)
            ->where('end_date', '>=', $period->start_date)
            ->first() ?? AcademicYear::first();

        // 1. Compute 5 Core KPIs
        $taskKpi = $this->kpiService->calculateTaskCompletionRate($employee, $start, $end);
        $reportKpi = $this->kpiService->calculateReportCompletionRate($employee, $start, $end, $taskKpi['total']);
        $onTimeKpi = $this->kpiService->calculateOnTimeRate($employee, $start, $end);
        $approvalKpi = $this->kpiService->calculateApprovalRate($employee, $start, $end);
        $targetKpi = $this->kpiService->calculateTargetAchievementRate($employee, $start, $end);

        $kpis = [
            'task_completion_rate' => $taskKpi['rate'],
            'report_completion_rate' => $reportKpi['rate'],
            'on_time_submission_rate' => $onTimeKpi['rate'],
            'approval_rate' => $approvalKpi['rate'],
            'target_achievement_rate' => $targetKpi['rate'],
        ];

        // 2. Compute Weighted Productivity Score & Rating
        $scoreResult = $this->scoreService->calculate($kpis, $customWeights);

        $metadata = [
            'total_tasks' => $taskKpi['total'],
            'completed_tasks' => $taskKpi['completed'],
            'expected_reports' => $reportKpi['expected'],
            'submitted_reports' => $reportKpi['completed'],
            'on_time_reports' => $onTimeKpi['on_time'],
            'late_reports' => $onTimeKpi['late'],
            'reviewed_reports' => $approvalKpi['reviewed'],
            'approved_reports' => $approvalKpi['approved'],
            'rejected_reports' => $approvalKpi['rejected'],
            'achieved_target_val' => $targetKpi['achieved'],
            'total_target_val' => $targetKpi['target'],
        ];

        return DB::transaction(function () use (
            $employee,
            $period,
            $academicYear,
            $kpis,
            $scoreResult,
            $metadata,
            $actor

        ) {
            $record = EmployeePerformanceRecord::firstOrNew([
                'employee_id' => $employee->id,
                'academic_year_id' => $academicYear?->id ?? 1,
                'period_type' => $period->period_type,
                'period_start' => $period->start_date,
                'period_end' => $period->end_date,
            ]);

            $isNew = ! $record->exists;
            $oldValues = $isNew ? [] : $record->toArray();

            $record->performance_period_id = $period->id;
            $record->completion_rate = $kpis['task_completion_rate'];
            $record->report_completion_rate = $kpis['report_completion_rate'];
            $record->on_time_rate = $kpis['on_time_submission_rate'];
            $record->approval_rate = $kpis['approval_rate'];
            $record->target_achievement_rate = $kpis['target_achievement_rate'];
            $record->overall_score = $scoreResult['score'];
            $record->productivity_score = $scoreResult['score'];
            $record->rating = $scoreResult['rating'];
            $record->calculation_version = $scoreResult['calculation_version'];
            $record->breakdown_data = $scoreResult['breakdown'];
            $record->metadata = $metadata;
            $record->calculated_at = now();
            $record->save();

            // Save individual score breakdowns
            $record->breakdowns()->delete();
            foreach ($scoreResult['breakdown'] as $b) {
                PerformanceScoreBreakdown::create([
                    'performance_record_id' => $record->id,
                    'metric_key' => $b['metric_key'],
                    'metric_label' => $b['metric_label'],
                    'raw_value' => $b['raw_value'],
                    'weight' => $b['weight'],
                    'weighted_value' => $b['weighted_value'],
                    'calculation_version' => $scoreResult['calculation_version'],
                ]);
            }

            // Also mirror snapshot into EmployeeProgressSnapshot for legacy dashboard compatibility
            if ($academicYear) {
                EmployeeProgressSnapshot::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'academic_year_id' => $academicYear->id,
                        'snapshot_date' => $period->end_date,
                    ],
                    [
                        'total_tasks' => $metadata['total_tasks'],
                        'completed_tasks' => $metadata['completed_tasks'],
                        'pending_tasks' => max(0, $metadata['total_tasks'] - $metadata['completed_tasks']),
                        'overdue_tasks' => $metadata['late_reports'],
                        'total_reports' => $metadata['expected_reports'],
                        'submitted_reports' => $metadata['submitted_reports'],
                        'approved_reports' => $metadata['approved_reports'],
                        'late_reports' => $metadata['late_reports'],
                        'completion_rate' => $kpis['task_completion_rate'],
                        'on_time_rate' => $kpis['on_time_submission_rate'],
                        'approval_rate' => $kpis['approval_rate'],
                        'target_achievement_rate' => $kpis['target_achievement_rate'],
                        'overall_score' => $scoreResult['score'],
                    ]
                );
            }

            // Audit log on manual recalculation
            if ($actor && ! $isNew) {
                AuditLog::forceCreate([
                    'user_id' => $actor->id,
                    'action' => 'performance.recalculated',
                    'entity_type' => EmployeePerformanceRecord::class,
                    'entity_id' => $record->id,
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode($record->toArray()),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $record;
        });
    }

    /**
     * Calculate and snapshot performance for all active employees for a monthly period.
     */
    public function calculateMonthlyPeriod(
        int $year,
        int $month,
        ?User $actor = null,
        ?string $reason = null
    ): array {
        $period = PerformancePeriod::findOrCreateMonthly($year, $month);
        $employees = Employee::query()->active()->get();

        $processed = 0;
        foreach ($employees as $employee) {
            $this->calculateEmployeePerformance($employee, $period, null, $actor, $reason);
            $processed++;
        }

        // Evaluate achievements & rankings for the period
        $achievementResult = $this->achievementService->evaluateMonthlyPeriod($period);

        $period->calculated_at = now();
        $period->save();

        return [
            'period' => $period,
            'processed_employees' => $processed,
            'achievements' => $achievementResult,
        ];
    }
}
