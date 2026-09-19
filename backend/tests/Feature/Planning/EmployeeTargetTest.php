<?php

namespace Tests\Feature\Planning;

use App\Models\AnnualPlan;
use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeTargetTest extends PlanningTestCase
{
    public function test_belongs_to_employee_annual_plan_and_department_target(): void
    {
        $context = $this->chain();
        $target = $context['employeeTarget'];

        $this->assertInstanceOf(BelongsTo::class, $target->employee());
        $this->assertInstanceOf(Employee::class, $target->employee);
        $this->assertSame($context['employee']->id, $target->employee->id);

        $this->assertInstanceOf(BelongsTo::class, $target->annualPlan());
        $this->assertInstanceOf(AnnualPlan::class, $target->annualPlan);

        $this->assertInstanceOf(BelongsTo::class, $target->departmentTarget());
        $this->assertInstanceOf(DepartmentTarget::class, $target->departmentTarget);
        $this->assertSame($context['departmentTarget']->id, $target->departmentTarget->id);
    }

    public function test_has_many_tasks(): void
    {
        $context = $this->chain();
        $target = $context['employeeTarget'];

        $this->assertInstanceOf(HasMany::class, $target->tasks());
        $this->assertTrue($target->tasks->contains($context['task']));
        $this->assertSame($target->id, $context['task']->employeeTarget->id);
    }

    public function test_casts_target_value_and_dates(): void
    {
        $target = EmployeeTarget::query()->create([
            'annual_plan_id' => $this->context()['annualPlan']->id,
            'employee_id' => $this->context()['employee']->id,
            'title' => 'Submit Research Papers',
            'target_value' => 5,
            'target_unit' => 'papers',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'active',
        ]);

        $this->assertSame('5.00', $target->fresh()->target_value);
        $this->assertInstanceOf(Carbon::class, $target->start_date);
        $this->assertInstanceOf(Carbon::class, $target->end_date);
    }

    public function test_scopes_active_and_completed(): void
    {
        $context = $this->context();

        $make = fn (string $status) => EmployeeTarget::query()->create([
            'annual_plan_id' => $context['annualPlan']->id,
            'employee_id' => $context['employee']->id,
            'title' => 'Target '.$status,
            'status' => $status,
        ]);

        $active = $make('active');
        $completed = $make('completed');

        $this->assertTrue(EmployeeTarget::query()->active()->pluck('id')->contains($active->id));
        $this->assertTrue(EmployeeTarget::query()->completed()->pluck('id')->contains($completed->id));
        $this->assertFalse(EmployeeTarget::query()->completed()->pluck('id')->contains($active->id));
    }

    public function test_employee_reverse_relationship_includes_target(): void
    {
        $context = $this->chain();

        $this->assertTrue(
            $context['employee']->employeeTargets()->pluck('id')->contains($context['employeeTarget']->id),
        );
    }

    public function test_mass_assignment_guards_protected_columns(): void
    {
        $target = new EmployeeTarget;

        $this->assertFalse($target->isFillable('id'));
        $this->assertFalse($target->isFillable('created_at'));
        $this->assertFalse($target->isFillable('updated_at'));
        $this->assertFalse($target->isFillable('deleted_at'));
        $this->assertTrue($target->isFillable('department_target_id'));
    }
}
