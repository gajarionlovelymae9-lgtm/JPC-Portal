<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Employee\EmployeePortalService;
use Illuminate\Support\Carbon;

/**
 * Explains one of the authenticated employee's own assigned tasks in plain
 * language, using only the task record (title, description, required output,
 * priority, deadline, progress, notes and review history).
 *
 * The lookup is always constrained to the signed-in employee by
 * EmployeePortalService, so a task title or code cannot expose another
 * employee's assignment.
 */
class ExplainMyTaskTool implements AiToolInterface, RoleAwareAiToolInterface
{
    public function __construct(private readonly EmployeePortalService $portalService) {}

    public function name(): string
    {
        return 'ExplainMyTaskTool';
    }

    public function description(): string
    {
        return "Explains one of the signed-in employee's assigned tasks in simple language, including what it asks for, its deadline, priority, progress and review notes.";
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'required' => false, 'description' => 'Free-text reference to the task, e.g. its title or TASK code.'],
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
        $reference = $this->extractReference((string) ($params['query'] ?? ''));

        if ($reference === '') {
            return $this->explainOpenTasks($user);
        }

        $matches = $this->portalService->tasks($user, [
            'search' => $reference,
            'per_page' => 10,
        ])['tasks'];

        if ($matches->isEmpty()) {
            return $this->noMatch($user, $reference);
        }

        if ($matches->count() > 1) {
            return $this->multipleMatches($reference, $matches);
        }

        return $this->explainOne($user, (int) $matches->first()['id']);
    }

    /**
     * Resolve a human phrase from the chat message to a searchable reference.
     */
    private function extractReference(string $query): string
    {
        $query = trim($query);

        if (preg_match('/(TASK-\d{4}-\d+)/i', $query, $match)) {
            return $match[1];
        }

        if (preg_match('/["\']([^"\']{3,})["\']/', $query, $match)) {
            return trim($match[1]);
        }

        if (preg_match('/\b(?:assignment|task)\s*#?\s*(\d+)\b/i', $query, $match)) {
            return $match[1];
        }

        $cleaned = preg_replace(
            '/\b(explain|describe|tell me about|what is|whats|what\'s|my|assigned|task|tasks|the|in|simple|language|using|please|about|details|detail|show|give|me)\b/i',
            ' ',
            $query,
        );

        return trim(preg_replace('/\s+/', ' ', (string) $cleaned), " \t\n\r\0\x0B?.!,:;");
    }

    private function explainOne(User $user, int $assignmentId): array
    {
        $task = $this->portalService->task($user, $assignmentId);

        $status = ucfirst(str_replace('_', ' ', (string) ($task['status'] ?? 'assigned')));
        $priority = ucfirst((string) ($task['priority'] ?? 'medium'));

        $lines = [
            ($task['title'] ?? 'Untitled task')
                .(($task['task_code'] ?? null) ? ' ('.$task['task_code'].')' : '').'.',
            "It is currently {$status}, {$priority} priority, and ".(int) ($task['progress'] ?? 0).
                '% complete.',
        ];

        if (! empty($task['deadline'])) {
            $lines[] = 'It is due '.Carbon::parse($task['deadline'])->format('F j, Y').'.';
        }

        if (! empty($task['description'])) {
            $lines[] = 'In simple terms: '.$task['description'];
        } else {
            $lines[] = 'No description was added to this task.';
        }

        if (! empty($task['requirements'])) {
            $lines[] = 'What is expected as output: '.$task['requirements'];
        }

        if (! empty($task['notes'])) {
            $lines[] = 'Notes on the assignment: '.$task['notes'];
        }

        $reviews = $task['reviews'] ?? [];

        if (is_array($reviews) && $reviews !== []) {
            $latest = collect($reviews)->last();
            if (is_array($latest)) {
                $decision = ucfirst(str_replace('_', ' ', (string) ($latest['decision'] ?? 'reviewed')));
                $lines[] = 'Latest reviewer decision: '.$decision
                    .(! empty($latest['comments']) ? ' — '.$latest['comments'] : '').'.';
            }
        }

        return [
            'tool_name' => $this->name(),
            'task' => $task,
            'answer' => implode(' ', $lines),
            'has_data' => true,
            'source' => 'employee_portal',
        ];
    }

    private function explainOpenTasks(User $user): array
    {
        $open = $this->portalService
            ->tasks($user, ['per_page' => 100])['tasks']
            ->filter(fn (array $task) => ! in_array($task['status'], ['completed', 'approved', 'cancelled'], true))
            ->take(3)
            ->values();

        if ($open->isEmpty()) {
            return [
                'tool_name' => $this->name(),
                'tasks' => [],
                'answer' => 'You have no open tasks to explain right now.',
                'has_data' => false,
                'source' => 'employee_portal',
            ];
        }

        $explanations = $open
            ->map(fn (array $task) => $task['title']
                .' — '.ucfirst(str_replace('_', ' ', (string) $task['status']))
                .', '.(int) $task['progress'].'% complete'
                .($task['deadline'] ? ', due '.Carbon::parse($task['deadline'])->format('F j, Y') : '').'.')
            ->implode(' ');

        return [
            'tool_name' => $this->name(),
            'tasks' => $open->all(),
            'answer' => 'Here are your current open tasks. '.$explanations
                .' Ask me to explain any one by its title or task code for the full instructions.',
            'has_data' => true,
            'source' => 'employee_portal',
        ];
    }

    private function multipleMatches(string $reference, iterable $matches): array
    {
        $listed = collect($matches)
            ->take(5)
            ->map(fn (array $task) => $task['title']
                .' ('.ucfirst(str_replace('_', ' ', (string) $task['status'])).')')
            ->implode('; ');

        return [
            'tool_name' => $this->name(),
            'candidates' => collect($matches)->take(10)->values()->all(),
            'answer' => "I found more than one task matching \"{$reference}\": {$listed}. "
                .'Please repeat the question with the exact title or task code so I explain the right one.',
            'has_data' => true,
            'source' => 'employee_portal',
        ];
    }

    private function noMatch(User $user, string $reference): array
    {
        $openTitles = $this->portalService
            ->tasks($user, ['per_page' => 10])['tasks']
            ->filter(fn (array $task) => ! in_array($task['status'], ['completed', 'approved', 'cancelled'], true))
            ->take(5)
            ->pluck('title')
            ->implode('; ');

        return [
            'tool_name' => $this->name(),
            'answer' => "I could not find a task matching \"{$reference}\" among your assignments."
                .($openTitles !== '' ? " Your open tasks are: {$openTitles}." : ''),
            'has_data' => false,
            'source' => 'employee_portal',
        ];
    }
}
