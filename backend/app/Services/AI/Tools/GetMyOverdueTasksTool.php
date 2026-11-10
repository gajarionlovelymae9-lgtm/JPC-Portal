<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Employee\EmployeePortalService;
use Illuminate\Support\Carbon;

/**
 * The authenticated employee's own overdue tasks. Scoped inside
 * EmployeePortalService by the authenticated user — no employee id is read
 * from request input.
 */
class GetMyOverdueTasksTool implements AiToolInterface, RoleAwareAiToolInterface
{
    public function __construct(private readonly EmployeePortalService $portalService) {}

    public function name(): string
    {
        return 'GetMyOverdueTasksTool';
    }

    public function description(): string
    {
        return "Lists the signed-in employee's own overdue tasks with how many days each is past its deadline.";
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
        return 'reports.view';
    }

    public function supports(User $user): bool
    {
        return $user->employee !== null && $user->hasPermission('reports.view');
    }

    public function execute(array $params, User $user): array
    {
        $year = (int) ($params['year'] ?? now()->year);
        $month = (int) ($params['month'] ?? now()->month);

        $dashboard = $this->portalService->dashboard($user, $year, $month);
        $overdueCount = (int) ($dashboard['kpis']['overdue_tasks'] ?? 0);

        $tasks = $this->portalService
            ->tasks($user, ['per_page' => 100])['tasks']
            ->filter(fn (array $task) => (bool) ($task['is_overdue'] ?? false))
            ->sortBy('deadline')
            ->take(10)
            ->map(fn (array $task) => [
                'assignment_id' => $task['id'],
                'title' => $task['title'],
                'deadline' => $task['deadline'],
                'status' => $task['status'],
                'progress' => $task['progress'],
                'priority' => $task['priority'],
                'days_overdue' => $this->daysOverdue($task['deadline']),
            ])
            ->values()
            ->all();

        $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return [
            'tool_name' => $this->name(),
            'period' => sprintf('%04d-%02d', $year, $month),
            'period_label' => $periodLabel,
            'metrics' => [
                'overdue_tasks' => max($overdueCount, count($tasks)),
                'listed_tasks' => count($tasks),
            ],
            'tasks' => $tasks,
            'answer' => $this->composeAnswer($periodLabel, $overdueCount, $tasks),
            'has_data' => $overdueCount > 0,
            'source' => 'employee_portal',
        ];
    }

    private function daysOverdue(?string $deadline): int
    {
        if ($deadline === null) {
            return 0;
        }

        return max(1, (int) Carbon::parse($deadline)->startOfDay()->diffInDays(now()->startOfDay()));
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     */
    private function composeAnswer(string $periodLabel, int $overdueCount, array $tasks): string
    {
        if ($overdueCount === 0) {
            return "Your task progress — {$periodLabel}. You have no overdue tasks.";
        }

        $details = collect($tasks)
            ->take(5)
            ->map(fn (array $task) => $task['title']
                .' ('.$task['days_overdue'].' day'.($task['days_overdue'] === 1 ? '' : 's').' overdue'
                .', '.(int) $task['progress'].'% complete)')
            ->implode('; ');

        return "Your task progress — {$periodLabel}. You have {$overdueCount} overdue "
            .($overdueCount === 1 ? 'task' : 'tasks')
            .($details !== '' ? ': '.$details.'.' : '.');
    }
}
