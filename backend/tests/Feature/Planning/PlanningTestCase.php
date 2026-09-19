<?php

namespace Tests\Feature\Planning;

use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\Position;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared fixture builder for the Phase 3.3 planning & task domain.
 *
 * Records are created with explicit model queries so each test controls the
 * exact values (the factories added in Phase 3.3-D are exercised separately
 * in PlanningFactoryTest).
 */
abstract class PlanningTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    protected ?Task $taskModel = null;

    protected ?TaskAssignment $assignmentModel = null;

    private int $taskSequence = 0;

    /**
     * Build the base planning records shared by most tests.
     *
     * @return array<string, mixed>
     */
    protected function context(): array
    {
        if ($this->context !== []) {
            return $this->context;
        }

        $user = User::factory()->create();
        $academicYear = AcademicYear::factory()->create();
        $department = Department::factory()->create();
        $position = Position::factory()->create();

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        $annualPlan = AnnualPlan::factory()->create([
            'academic_year_id' => $academicYear->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        $departmentPlan = DepartmentPlan::query()->create([
            'annual_plan_id' => $annualPlan->id,
            'department_id' => $department->id,
            'title' => 'Department Plan '.$department->code,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $departmentTarget = DepartmentTarget::query()->create([
            'department_plan_id' => $departmentPlan->id,
            'department_id' => $department->id,
            'target_name' => 'Faculty Documentation Completion',
            'target_value' => 100,
            'unit' => 'percent',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $employeeTarget = EmployeeTarget::query()->create([
            'annual_plan_id' => $annualPlan->id,
            'department_target_id' => $departmentTarget->id,
            'employee_id' => $employee->id,
            'title' => 'Complete Assigned Academic Reports',
            'target_value' => 12,
            'target_unit' => 'reports',
            'status' => 'active',
        ]);

        return $this->context = compact(
            'user',
            'academicYear',
            'department',
            'position',
            'employee',
            'annualPlan',
            'departmentPlan',
            'departmentTarget',
            'employeeTarget',
        );
    }

    /**
     * Create a task wired to the shared planning chain.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeTask(array $overrides = []): Task
    {
        $context = $this->context();

        $this->taskSequence++;

        return Task::query()->create(array_merge([
            'annual_plan_id' => $context['annualPlan']->id,
            'department_plan_id' => $context['departmentPlan']->id,
            'department_target_id' => $context['departmentTarget']->id,
            'employee_target_id' => $context['employeeTarget']->id,
            'department_id' => $context['department']->id,
            'task_code' => 'TASK-TEST-'.str_pad((string) $this->taskSequence, 4, '0', STR_PAD_LEFT),
            'title' => 'Prepare Monthly Faculty Report',
            'description' => 'Compile and submit the monthly faculty accomplishment report.',
            'priority' => 'high',
            'status' => 'assigned',
            'start_date' => now()->subDays(3),
            'due_date' => now()->addDays(7),
            'estimated_hours' => 8.5,
            'created_by' => $context['user']->id,
        ], $overrides));
    }

    /**
     * Create a task assignment wired to the shared chain.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeAssignment(Task $task, array $overrides = []): TaskAssignment
    {
        $context = $this->context();

        return TaskAssignment::query()->create(array_merge([
            'task_id' => $task->id,
            'employee_id' => $context['employee']->id,
            'assigned_by' => $context['user']->id,
            'assigned_at' => now(),
            'status' => 'in_progress',
            'due_date' => now()->addDays(7),
            'progress_percentage' => 65,
        ], $overrides));
    }

    /**
     * Build the full chain, creating the canonical task and assignment once.
     *
     * @return array<string, mixed>
     */
    protected function chain(): array
    {
        $context = $this->context();

        $this->taskModel ??= $this->makeTask();
        $this->assignmentModel ??= $this->makeAssignment($this->taskModel);

        return $context + [
            'task' => $this->taskModel,
            'assignment' => $this->assignmentModel,
        ];
    }
}
