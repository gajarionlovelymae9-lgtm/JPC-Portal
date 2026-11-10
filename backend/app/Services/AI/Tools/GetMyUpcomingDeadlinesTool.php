<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Employee\EmployeePortalService;
use Illuminate\Support\Carbon;

/**
 * Upcoming task deadlines for the authenticated employee (next 14 days, as
 * computed by EmployeePortalService). No other employee's deadlines can be
 * read because the service scopes every query by the authenticated user.
 */
class GetMyUpcomingDeadlinesTool implements AiToolInterface, RoleAwareAiToolInterface
{
    public function __construct(private readonly EmployeePortalService $portalService) {}

    public function name(): string
    {
        return 'GetMyUpcomingDeadlinesTool';
    }

    public function description(): string
    {
        return "Lists the signed-in employee's own task deadlines coming up in the next 14 days, ordered by urgency.";
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

        $deadlines = collect($dashboard['deadlines'] ?? [])
            ->where('type', 'task')
            ->sortBy('deadline')
            ->take(10)
            ->map(fn (array $deadline) => [
                'assignment_id' => $deadline['id'],
                'title' => $deadline['title'],
                'deadline' => $deadline['deadline'],
                'deadline_date' => $deadline['deadline_date'],
                'days_remaining' => $deadline['days_remaining'],
                'status' => $deadline['status'],
                'urgency' => $deadline['urgency'],
            ])
            ->values()
            ->all();

        $periodLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        return [
            'tool_name' => $this->name(),
            'period' => sprintf('%04d-%02d', $year, $month),
            'period_label' => $periodLabel,
            'metrics' => ['upcoming_deadlines' => count($deadlines)],
            'deadlines' => $deadlines,
            'answer' => $this->composeAnswer($periodLabel, $deadlines),
            'has_data' => $deadlines !== [],
            'source' => 'employee_portal',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $deadlines
     */
    private function composeAnswer(string $periodLabel, array $deadlines): string
    {
        if ($deadlines === []) {
            return "Your task progress — {$periodLabel}. No task deadlines are coming up in the next 14 days.";
        }

        $details = collect($deadlines)
            ->take(5)
            ->map(fn (array $deadline) => $deadline['title']
                .' — '.$this->relativeDeadline((int) $deadline['days_remaining']))
            ->implode('; ');

        return "Your upcoming task deadlines — {$periodLabel}. {$details}.";
    }

    private function relativeDeadline(int $days): string
    {
        return match (true) {
            $days === 0 => 'due today',
            $days === 1 => 'due tomorrow',
            $days > 1 => 'due in '.$days.' days',
            default => 'overdue',
        };
    }
}
