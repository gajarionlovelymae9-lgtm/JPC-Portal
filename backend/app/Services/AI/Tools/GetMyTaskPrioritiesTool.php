<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Employee\EmployeePortalService;
use Illuminate\Support\Carbon;

/**
 * Ranked priorities from the authenticated employee's own open tasks.
 *
 * The ranking is deterministic and explainable: overdue work first, then task
 * priority (urgent → high → medium → low), then the nearest deadline, then the
 * least-progressed task. Nothing is invented — every field comes from
 * EmployeePortalService::tasks() for the signed-in employee.
 */
class GetMyTaskPrioritiesTool implements AiToolInterface, RoleAwareAiToolInterface
{
    /** @var array<string, int> */
    private const PRIORITY_WEIGHT = [
        'urgent' => 0,
        'high' => 1,
        'medium' => 2,
        'low' => 3,
    ];

    private const OPEN_STATUSES = ['assigned', 'accepted', 'pending', 'in_progress', 'submitted', 'rejected'];

    public function __construct(private readonly EmployeePortalService $portalService) {}

    public function name(): string
    {
        return 'GetMyTaskPrioritiesTool';
    }

    public function description(): string
    {
        return "Ranks the signed-in employee's own open tasks by urgency, priority, deadline and progress so they know what to work on next.";
    }

    public function parameters(): array
    {
        return [
            'limit' => ['type' => 'integer', 'required' => false],
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
        $limit = max(1, min((int) ($params['limit'] ?? 5), 10));

        $openTasks = $this->portalService
            ->tasks($user, ['per_page' => 100])['tasks']
            ->filter(fn (array $task) => in_array($task['status'], self::OPEN_STATUSES, true))
            ->values();

        $priorities = $openTasks
            ->map(fn (array $task) => array_merge($task, [
                '_priority_rank' => self::PRIORITY_WEIGHT[$task['priority'] ?? 'medium'] ?? 2,
                '_deadline_key' => $task['deadline'] ?? '9999-12-31T23:59:59+00:00',
            ]))
            ->sortBy([
                ['_priority_rank', 'asc'],
                ['_deadline_key', 'asc'],
                ['progress', 'asc'],
            ])
            ->take($limit)
            ->map(fn (array $task) => [
                'assignment_id' => $task['id'],
                'title' => $task['title'],
                'priority' => $task['priority'],
                'status' => $task['status'],
                'progress' => $task['progress'],
                'deadline' => $task['deadline'],
                'is_overdue' => $task['is_overdue'],
                'reason' => $this->reason($task),
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
                'open_tasks' => $openTasks->count(),
                'priorities_returned' => count($priorities),
            ],
            'priorities' => $priorities,
            'answer' => $this->composeAnswer($periodLabel, $priorities, $openTasks->count()),
            'has_data' => $priorities !== [],
            'source' => 'employee_portal',
        ];
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function reason(array $task): string
    {
        $reasons = [];

        if ($task['is_overdue']) {
            $reasons[] = 'overdue by '.$this->daysOverdue($task['deadline']).' day'
                .($this->daysOverdue($task['deadline']) === 1 ? '' : 's');
        } elseif ($task['deadline'] !== null) {
            $days = $this->daysRemaining($task['deadline']);
            $reasons[] = match (true) {
                $days === 0 => 'due today',
                $days === 1 => 'due tomorrow',
                $days > 1 => 'due in '.$days.' days',
                default => 'deadline elapsed',
            };
        }

        $reasons[] = ucfirst((string) ($task['priority'] ?? 'medium')).' priority';
        $reasons[] = (int) $task['progress'].'% complete';

        return implode(', ', $reasons);
    }

    private function daysRemaining(?string $deadline): int
    {
        if ($deadline === null) {
            return 0;
        }

        return (int) Carbon::now()->startOfDay()
            ->diffInDays(Carbon::parse($deadline)->startOfDay(), false);
    }

    private function daysOverdue(?string $deadline): int
    {
        if ($deadline === null) {
            return 0;
        }

        return max(1, abs($this->daysRemaining($deadline)));
    }

    /**
     * @param  list<array<string, mixed>>  $priorities
     */
    private function composeAnswer(string $periodLabel, array $priorities, int $openCount): string
    {
        if ($priorities === []) {
            return "Your task priorities — {$periodLabel}. You have no open tasks to prioritise right now.";
        }

        $ranking = collect($priorities)
            ->map(fn (array $task, int $index) => ($index + 1).'. '.$task['title'].' ('.$task['reason'].')')
            ->implode(' ');

        return "Your task priorities — {$periodLabel}. Based on {$openCount} open "
            .($openCount === 1 ? 'task' : 'tasks').', start with: '.$ranking;
    }
}
