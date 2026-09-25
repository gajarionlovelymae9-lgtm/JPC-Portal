<?php

namespace App\Services\DataQuality\Scanners;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\DataQuality\DataQualityRuleRegistry;
use Closure;

class TaskDataScanner
{
    public function __construct(
        private readonly DataQualityRuleRegistry $registry
    ) {}

    public function scan(?int $departmentId, ?int $employeeId, Closure $onIssueFound, Closure $onRecordChecked): void
    {
        $taskRules = $this->registry->getRulesFor('task');
        $assignmentRules = $this->registry->getRulesFor('task_assignment');

        $query = Task::query();
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $query->chunk(100, function ($tasks) use ($taskRules, $onIssueFound, $onRecordChecked) {
            foreach ($tasks as $task) {
                $onRecordChecked();

                foreach ($taskRules as $rule) {
                    $issue = $rule->check($task);
                    if ($issue) {
                        $onIssueFound('task', $task->id, $issue);
                    }
                }
            }
        });

        // Scan task assignments for orphans
        $assignmentQuery = TaskAssignment::query();
        if ($employeeId) {
            $assignmentQuery->where('employee_id', $employeeId);
        }

        $assignmentQuery->chunk(100, function ($assignments) use ($assignmentRules, $onIssueFound, $onRecordChecked) {
            foreach ($assignments as $assignment) {
                $onRecordChecked();

                foreach ($assignmentRules as $rule) {
                    $issue = $rule->check($assignment);
                    if ($issue) {
                        $onIssueFound('task_assignment', $assignment->id, $issue);
                    }
                }
            }
        });
    }
}
