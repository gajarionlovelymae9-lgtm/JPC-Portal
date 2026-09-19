<?php

namespace Tests\Feature\Planning;

use App\Models\Department;
use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\EmployeeTarget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentTargetTest extends PlanningTestCase
{
    public function test_persists_and_casts_values(): void
    {
        $target = DepartmentTarget::query()->create([
            'department_plan_id' => $this->context()['departmentPlan']->id,
            'department_id' => $this->context()['department']->id,
            'target_name' => 'Research Output',
            'target_value' => 25.5,
            'unit' => 'papers',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('department_targets', ['id' => $target->id]);
        $this->assertSame('25.50', $target->fresh()->target_value);
        $this->assertInstanceOf(Carbon::class, $target->start_date);
        $this->assertInstanceOf(Carbon::class, $target->end_date);
    }

    public function test_belongs_to_plan_and_department(): void
    {
        $context = $this->chain();
        $target = $context['departmentTarget'];

        $this->assertInstanceOf(BelongsTo::class, $target->departmentPlan());
        $this->assertSame($context['departmentPlan']->id, $target->departmentPlan->id);
        $this->assertInstanceOf(BelongsTo::class, $target->department());
        $this->assertInstanceOf(Department::class, $target->department);
    }

    public function test_belongs_to_creator(): void
    {
        $context = $this->chain();

        $this->assertInstanceOf(BelongsTo::class, $context['departmentTarget']->createdBy());
        $this->assertSame($context['user']->id, $context['departmentTarget']->createdBy->id);
    }

    public function test_has_many_employee_targets_and_tasks(): void
    {
        $context = $this->chain();
        $target = $context['departmentTarget'];

        $this->assertInstanceOf(HasMany::class, $target->employeeTargets());
        $this->assertTrue($target->employeeTargets->contains($context['employeeTarget']));

        $this->assertInstanceOf(HasMany::class, $target->tasks());
        $this->assertTrue($target->tasks->contains($context['task']));
    }

    public function test_scopes_draft_active_completed(): void
    {
        $context = $this->context();

        $make = fn (string $status) => DepartmentTarget::query()->create([
            'department_plan_id' => $context['departmentPlan']->id,
            'department_id' => $context['department']->id,
            'target_name' => 'Target '.$status,
            'status' => $status,
        ]);

        $draft = $make('draft');
        $active = $make('active');
        $completed = $make('completed');

        $this->assertTrue(DepartmentTarget::query()->draft()->pluck('id')->contains($draft->id));
        $this->assertTrue(DepartmentTarget::query()->active()->pluck('id')->contains($active->id));
        $this->assertTrue(DepartmentTarget::query()->completed()->pluck('id')->contains($completed->id));
    }

    public function test_soft_delete_uses_trash_bin(): void
    {
        $target = $this->context()['departmentTarget'];

        $target->delete();

        $this->assertSoftDeleted('department_targets', ['id' => $target->id]);
        $this->assertNotNull(DepartmentTarget::withTrashed()->find($target->id));
    }

    public function test_mass_assignment_guards_protected_columns(): void
    {
        $target = new DepartmentTarget;

        $this->assertFalse($target->isFillable('id'));
        $this->assertFalse($target->isFillable('created_at'));
        $this->assertFalse($target->isFillable('updated_at'));
        $this->assertFalse($target->isFillable('deleted_at'));
        $this->assertTrue($target->isFillable('department_plan_id'));
        $this->assertTrue($target->isFillable('target_name'));
    }

    public function test_plan_owns_targets(): void
    {
        $context = $this->chain();
        $plan = $context['departmentPlan'];

        $this->assertInstanceOf(DepartmentPlan::class, $context['departmentTarget']->departmentPlan);
        $this->assertTrue($plan->departmentTargets->contains($context['departmentTarget']));
        $this->assertInstanceOf(EmployeeTarget::class, $context['departmentTarget']->employeeTargets->first());
    }
}
