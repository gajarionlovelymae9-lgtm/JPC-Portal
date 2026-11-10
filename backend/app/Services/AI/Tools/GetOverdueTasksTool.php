<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Admin\AdminMonitoringService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Overdue task register for administrators, with the responsible employees.
 *
 * Grounded in AdminMonitoringService (task_assignments + tasks). Department
 * heads receive only their own department; employees are routed to the
 * employee-scoped tool instead (see RoleAwareAiToolInterface).
 */
class GetOverdueTasksTool implements AiToolInterface, RoleAwareAiToolInterface
{
    public function __construct(private readonly AdminMonitoringService $monitoringService) {}

    public function name(): string
    {
        return 'GetOverdueTasksTool';
    }

    public function description(): string
    {
        return 'Lists overdue task assignments in the caller scope, including the employee, department, priority and days overdue.';
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
        $overdueCount = (int) $summary['overdue'];

        $rows = $this->monitoringService
            ->tasks($filters + ['overdue' => true, 'per_page' => 100])
            ->items();

        $tasks = collect($rows)
            ->take(10)
            ->map(fn (array $row) => [
                'assignment_id' => $row['assignment_id'],
                'task_name' => $row['task_name'],
                'task_code' => $row['task_code'],
                'employee' => $row['assigned_to'],
                'department' => $row['department'],
                'priority' => $row['priority'],
                'due_date' => $row['due_date'],
                'days_overdue' => $row['days_overdue'],
                'progress' => $row['progress'],
            ])
            ->values()
            ->all();

        $year = (int) ($params['year'] ?? now()->year);
        $month = (int) ($params['month'] ?? now()->month);
        $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return [
            'tool_name' => $this->name(),
            'period' => sprintf('%04d-%02d', $year, $month),
            'period_label' => $periodLabel,
            'metrics' => [
                'overdue_tasks' => $overdueCount,
                'listed_tasks' => count($tasks),
            ],
            'tasks' => $tasks,
            'answer' => $this->composeAnswer($periodLabel, $overdueCount, $tasks),
            'has_data' => $overdueCount > 0,
            'source' => 'task_monitoring',
        ];
    }

    /**
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
     * @param  list<array<string, mixed>>  $tasks
     */
    private function composeAnswer(string $periodLabel, int $overdueCount, array $tasks): string
    {
        if ($overdueCount === 0) {
            return "Task monitoring — {$periodLabel}. There are no overdue task assignments in your scope.";
        }

        $headline = "Task monitoring — {$periodLabel}. {$overdueCount} task "
            .($overdueCount === 1 ? 'assignment is' : 'assignments are').' overdue.';

        $details = collect($tasks)
            ->take(5)
            ->map(fn (array $task) => $task['task_name']
                .' — '.($task['employee'] ?? 'Unassigned')
                .' ('.($task['department'] ?? 'No department').')'
                .', '.$task['days_overdue'].'d overdue'
                .', priority '.(ucfirst((string) ($task['priority'] ?? 'normal'))))
            ->implode('; ');

        return trim($headline.' '.($details !== '' ? 'Oldest items: '.$details.'.' : ''));
    }
}
