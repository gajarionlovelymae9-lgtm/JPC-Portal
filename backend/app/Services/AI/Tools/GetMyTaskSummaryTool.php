<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Employee\EmployeePortalService;
use Illuminate\Support\Carbon;

/**
 * The authenticated employee's own task progress.
 *
 * EmployeePortalService resolves the employee from the authenticated user, so
 * no employee identifier can be supplied by the caller. The tool therefore
 * cannot read another employee's records.
 */
class GetMyTaskSummaryTool implements AiToolInterface, RoleAwareAiToolInterface
{
    public function __construct(private readonly EmployeePortalService $portalService) {}

    public function name(): string
    {
        return 'GetMyTaskSummaryTool';
    }

    public function description(): string
    {
        return "Summarises the signed-in employee's own task progress: total, completed, pending, in-progress, submitted and overdue tasks, completion rate, upcoming deadlines and recent work.";
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
        $kpis = $dashboard['kpis'] ?? [];

        $total = (int) ($kpis['total_tasks'] ?? 0);
        $completed = (int) ($kpis['completed_tasks'] ?? 0);
        $pending = (int) ($kpis['pending_tasks'] ?? 0);
        $inProgress = (int) ($kpis['in_progress_tasks'] ?? 0);
        $submitted = (int) ($kpis['submitted_tasks'] ?? 0);
        $overdue = (int) ($kpis['overdue_tasks'] ?? 0);
        $completionRate = (float) ($kpis['completion_rate'] ?? 0);

        $upcoming = collect($dashboard['deadlines'] ?? [])
            ->where('type', 'task')
            ->take(5)
            ->map(fn (array $deadline) => [
                'assignment_id' => $deadline['id'],
                'title' => $deadline['title'],
                'deadline_date' => $deadline['deadline_date'],
                'days_remaining' => $deadline['days_remaining'],
                'status' => $deadline['status'],
            ])
            ->values()
            ->all();

        $overdueTasks = collect($dashboard['overdue'] ?? [])
            ->take(5)
            ->map(fn (array $task) => [
                'assignment_id' => $task['id'],
                'title' => $task['title'],
                'deadline' => $task['deadline'],
                'days_overdue' => $task['days_overdue'],
                'status' => $task['status'],
            ])
            ->values()
            ->all();

        $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return [
            'tool_name' => $this->name(),
            'period' => sprintf('%04d-%02d', $year, $month),
            'period_label' => $periodLabel,
            'employee' => $dashboard['employee'] ?? null,
            'metrics' => [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'pending_tasks' => $pending,
                'in_progress_tasks' => $inProgress,
                'submitted_tasks' => $submitted,
                'overdue_tasks' => $overdue,
                'completion_rate' => $completionRate,
            ],
            'progress_percent' => (float) ($dashboard['progress']['percent'] ?? $completionRate),
            'upcoming_deadlines' => $upcoming,
            'overdue_tasks' => $overdueTasks,
            'answer' => $this->composeAnswer(
                $periodLabel,
                $total,
                $completed,
                $pending,
                $inProgress,
                $submitted,
                $overdue,
                $completionRate,
                $upcoming,
            ),
            'has_data' => $total > 0,
            'source' => 'employee_portal',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $upcoming
     */
    private function composeAnswer(
        string $periodLabel,
        int $total,
        int $completed,
        int $pending,
        int $inProgress,
        int $submitted,
        int $overdue,
        float $completionRate,
        array $upcoming,
    ): string {
        if ($total === 0) {
            return "Your task progress — {$periodLabel}. You have no task assignments yet.";
        }

        $lines = [
            "Your task progress — {$periodLabel}.",
            "You have {$total} task ".($total === 1 ? 'assignment' : 'assignments').": "
                ."{$completed} completed ({$completionRate}%), {$inProgress} in progress, "
                ."{$submitted} submitted for review, {$pending} not started, and {$overdue} overdue.",
        ];

        if ($upcoming !== []) {
            $lines[] = 'Coming up: '.collect($upcoming)
                ->map(fn (array $task) => $task['title']
                    .' ('.($task['days_remaining'] === 0
                        ? 'due today'
                        : 'in '.$task['days_remaining'].' day'.($task['days_remaining'] === 1 ? '' : 's')).')')
                ->implode(', ').'.';
        }

        return implode(' ', $lines);
    }
}
