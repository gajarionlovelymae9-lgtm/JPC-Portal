<?php

namespace App\Services\AI\Tools;

use App\Models\Department;
use App\Models\User;
use App\Services\Admin\AdminMonitoringService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Institution / department task-monitoring summary for administrators.
 *
 * Every figure comes from AdminMonitoringService over the real tasks and
 * task_assignments tables. A department head is automatically limited to their
 * own department; employees are excluded before execution by supports().
 */
class GetTaskMonitoringSummaryTool implements AiToolInterface, RoleAwareAiToolInterface
{
    public function __construct(private readonly AdminMonitoringService $monitoringService) {}

    public function name(): string
    {
        return 'GetTaskMonitoringSummaryTool';
    }

    public function description(): string
    {
        return 'Summarises task monitoring for the caller scope: total, completed, in-progress, not-started and overdue task assignments, the employees with the most pending/overdue work, and department completion rates.';
    }

    public function parameters(): array
    {
        return [
            'year' => ['type' => 'integer', 'required' => false],
            'month' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'analytics.view';
    }

    public function supports(User $user): bool
    {
        return $user->hasPermission('analytics.view')
            && $user->hasAnyRole(['admin', 'hr', 'department_head']);
    }

    public function execute(array $params, User $user): array
    {
        $filters = $this->scopeFilters($user);
        $summary = $this->monitoringService->taskSummary($filters);

        $total = (int) $summary['total'];
        $completed = (int) $summary['completed'];
        $inProgress = (int) $summary['in_progress'];
        $notStarted = (int) $summary['not_started'];
        $overdue = (int) $summary['overdue'];
        $pending = $inProgress + $notStarted + $overdue;
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

        $pendingRows = $this->monitoringService
            ->tasks($filters + ['status' => AdminMonitoringService::TASK_PENDING, 'per_page' => 100])
            ->items();

        $overdueRows = $this->monitoringService
            ->tasks($filters + ['overdue' => true, 'per_page' => 100])
            ->items();

        $pendingByEmployee = $this->groupByEmployee($pendingRows);
        $overdueByEmployee = $this->groupByEmployee($overdueRows);
        $departments = $this->departmentBreakdown($filters);

        $year = (int) ($params['year'] ?? now()->year);
        $month = (int) ($params['month'] ?? now()->month);
        $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return [
            'tool_name' => $this->name(),
            'period' => sprintf('%04d-%02d', $year, $month),
            'period_label' => $periodLabel,
            'scope' => isset($filters['department_id'])
                ? 'department:'.($departments[0]['department'] ?? $filters['department_id'])
                : 'institution',
            'metrics' => [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'pending_tasks' => $pending,
                'in_progress_tasks' => $inProgress,
                'not_started_tasks' => $notStarted,
                'overdue_tasks' => $overdue,
                'completion_rate' => $completionRate,
            ],
            'pending_by_employee' => $pendingByEmployee,
            'overdue_by_employee' => $overdueByEmployee,
            'departments' => $departments,
            'answer' => $this->composeAnswer(
                $periodLabel,
                $total,
                $completed,
                $pending,
                $inProgress,
                $notStarted,
                $overdue,
                $completionRate,
                $pendingByEmployee,
                $overdueByEmployee,
                $departments,
            ),
            'has_data' => $total > 0,
            'source' => 'task_monitoring',
        ];
    }

    /**
     * Department heads may only read their own department. Administrators and
     * HR read institution-wide through the same service.
     *
     * @return array<string, mixed>
     */
    private function scopeFilters(User $user): array
    {
        if (! $user->hasRole('department_head')) {
            return [];
        }

        $departmentId = $user->employee?->department_id;

        if ($departmentId === null) {
            throw new AuthorizationException('Your account is not linked to a department.');
        }

        return ['department_id' => (int) $departmentId];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{employee: string, count: int}>
     */
    private function groupByEmployee(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row) => $row['assigned_to'] ?? 'Unassigned')
            ->map(fn ($group, $name) => ['employee' => (string) $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Per-department progress using the same taskSummary contract, so the
     * department view can never disagree with the headline counts.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function departmentBreakdown(array $filters): array
    {
        $departments = Department::query()
            ->when(
                isset($filters['department_id']),
                fn ($query) => $query->whereKey((int) $filters['department_id']),
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        return $departments->map(function (Department $department) {
            $summary = $this->monitoringService->taskSummary(['department_id' => $department->id]);
            $total = (int) $summary['total'];
            $completed = (int) $summary['completed'];

            return [
                'department_id' => $department->id,
                'department' => $department->name,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'overdue_tasks' => (int) $summary['overdue'],
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{employee: string, count: int}>  $pendingByEmployee
     * @param  list<array{employee: string, count: int}>  $overdueByEmployee
     * @param  list<array<string, mixed>>  $departments
     */
    private function composeAnswer(
        string $periodLabel,
        int $total,
        int $completed,
        int $pending,
        int $inProgress,
        int $notStarted,
        int $overdue,
        float $completionRate,
        array $pendingByEmployee,
        array $overdueByEmployee,
        array $departments,
    ): string {
        if ($total === 0) {
            return "Task monitoring — {$periodLabel}. No task assignments match your scope yet.";
        }

        $lines = [
            "Task monitoring — {$periodLabel}.",
            "There are {$total} task assignments: {$completed} completed ({$completionRate}%), "
                ."{$inProgress} in progress, {$notStarted} not started, and {$overdue} overdue. "
                ."That leaves {$pending} tasks still open.",
        ];

        if ($overdueByEmployee !== []) {
            $lines[] = 'Employees with overdue work: '.$this->formatPeople($overdueByEmployee).'.';
        }

        if ($pendingByEmployee !== []) {
            $lines[] = 'Employees with the most open work: '.$this->formatPeople($pendingByEmployee).'.';
        }

        if ($departments !== []) {
            $lines[] = 'Department completion: '.$this->formatDepartments($departments).'.';
        }

        return implode(' ', $lines);
    }

    /**
     * @param  list<array{employee: string, count: int}>  $people
     */
    private function formatPeople(array $people): string
    {
        return collect($people)
            ->take(5)
            ->map(fn (array $row) => $row['employee'].' ('.$row['count'].')')
            ->implode(', ');
    }

    /**
     * @param  list<array<string, mixed>>  $departments
     */
    private function formatDepartments(array $departments): string
    {
        return collect($departments)
            ->take(6)
            ->map(fn (array $row) => $row['department'].' '.$row['completed_tasks'].'/'.$row['total_tasks']
                .' ('.$row['completion_rate'].'%)')
            ->implode('; ');
    }
}
