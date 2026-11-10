<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PerformanceCalculationService
{
    public function overview(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $query = Employee::query()->active()->with('department');

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        } elseif ($bag->departmentId !== null) {
            $query->where('department_id', $bag->departmentId);
        }

        $total = $query->count();
        $records = EmployeePerformanceRecord::query()
            ->whereIn('employee_id', $query->pluck('id'))
            ->where('academic_year_id', $bag->academicYearId ?? config('reports.default_academic_year_id'))
            ->get();

        $avgCompletion = $records->avg('completion_rate') ?? 0;
        $avgOnTime = $records->avg('on_time_rate') ?? 0;
        $avgApproval = $records->avg('approval_rate') ?? 0;
        $avgTarget = $records->avg('target_achievement_rate') ?? 0;
        $avgOverdue = $records->avg('overdue_rate') ?? 0;
        $avgRevision = $records->avg('revision_rate') ?? 0;

        return [
            'total_employees' => $total,
            'avg_completion_rate' => round($avgCompletion, 2),
            'avg_on_time_rate' => round($avgOnTime, 2),
            'avg_approval_rate' => round($avgApproval, 2),
            'avg_target_achievement' => round($avgTarget, 2),
            'avg_overdue_rate' => round($avgOverdue, 2),
            'avg_revision_rate' => round($avgRevision, 2),
            'department_id' => $departmentId,
            'academic_year_id' => $bag->academicYearId ?? config('reports.default_academic_year_id'),
        ];
    }

    public function leaderboard(User $user, ReportFilterBag $bag, ?int $departmentId): array
    {
        $query = Employee::query()->active()->with('department')
            ->whereHas('performanceRecords', fn (Builder $q) => $q->where('academic_year_id', $bag->academicYearId ?? config('reports.default_academic_year_id')));

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        } elseif ($bag->departmentId !== null) {
            $query->where('department_id', $bag->departmentId);
        }

        $employees = $query->get();
        $records = EmployeePerformanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->where('academic_year_id', $bag->academicYearId ?? config('reports.default_academic_year_id'))
            ->get()
            ->keyBy('employee_id');

        $rows = $employees->map(fn (Employee $e) => [
            'employee_id' => $e->id,
            'employee_name' => trim(implode(' ', array_filter([$e->first_name, $e->middle_name, $e->last_name]))),
            'department_name' => $e->department?->name ?? '—',
            'completion_rate' => $records->has($e->id) ? round($records[$e->id]->completion_rate, 2) : 0,
            'on_time_rate' => $records->has($e->id) ? round($records[$e->id]->on_time_rate, 2) : 0,
            'approval_rate' => $records->has($e->id) ? round($records[$e->id]->approval_rate, 2) : 0,
            'target_achievement' => $records->has($e->id) ? round($records[$e->id]->target_achievement_rate, 2) : 0,
            'overall_score' => $records->has($e->id) ? round($records[$e->id]->overall_score, 2) : 0,
        ])->sortByDesc('overall_score')->values()->all();

        return [
            'leaderboard' => $rows,
            'period' => $bag->academicYearId ?? config('reports.default_academic_year_id'),
            'total' => count($rows),
        ];
    }

    public function calculateScore(EmployeePerformanceRecord $record, array $weights): float
    {
        $score = 0.0;
        foreach ($weights as $field => $weight) {
            $value = $record->$field ?? 0;
            $score += (float) $value * (float) $weight;
        }

        return round($score, 2);
    }

    public function paginatedLeaderboard(User $user, ReportFilterBag $bag, ?int $departmentId): LengthAwarePaginator
    {
        $query = Employee::query()->active()->with('department')
            ->whereHas('performanceRecords', fn (Builder $q) => $q->where('academic_year_id', $bag->academicYearId ?? config('reports.default_academic_year_id')));

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        } elseif ($bag->departmentId !== null) {
            $query->where('department_id', $bag->departmentId);
        }

        return $query->paginate($bag->perPage);
    }
}
