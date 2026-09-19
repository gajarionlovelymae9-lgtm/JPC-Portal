<?php

namespace Tests\Feature\Planning;

use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\DepartmentPlan;
use App\Models\DepartmentTarget;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentPlanTest extends PlanningTestCase
{
    public function test_persists_and_casts_approved_at(): void
    {
        $plan = DepartmentPlan::query()->create([
            'annual_plan_id' => $this->context()['annualPlan']->id,
            'department_id' => $this->context()['department']->id,
            'title' => 'CBA Department Plan 2026',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->assertDatabaseHas('department_plans', ['id' => $plan->id, 'status' => 'approved']);
        $this->assertInstanceOf(Carbon::class, $plan->approved_at);
    }

    public function test_belongs_to_annual_plan_and_department(): void
    {
        $context = $this->chain();
        $plan = $context['departmentPlan'];

        $this->assertInstanceOf(BelongsTo::class, $plan->annualPlan());
        $this->assertSame($context['annualPlan']->id, $plan->annualPlan->id);
        $this->assertInstanceOf(BelongsTo::class, $plan->department());
        $this->assertSame($context['department']->id, $plan->department->id);
    }

    public function test_belongs_to_creator_and_approver(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $plan = DepartmentPlan::query()->create([
            'annual_plan_id' => $this->context()['annualPlan']->id,
            'department_id' => $this->context()['department']->id,
            'title' => 'Approved Department Plan',
            'created_by' => $creator->id,
            'approved_by' => $approver->id,
        ]);

        $this->assertInstanceOf(User::class, $plan->createdBy);
        $this->assertSame($creator->id, $plan->createdBy->id);
        $this->assertInstanceOf(User::class, $plan->approvedBy);
        $this->assertSame($approver->id, $plan->approvedBy->id);
    }

    public function test_has_many_department_targets_and_tasks(): void
    {
        $context = $this->chain();
        $plan = $context['departmentPlan'];

        $this->assertInstanceOf(HasMany::class, $plan->departmentTargets());
        $this->assertTrue($plan->departmentTargets->contains($context['departmentTarget']));

        $this->assertInstanceOf(HasMany::class, $plan->tasks());
        $this->assertTrue($plan->tasks->contains($context['task']));
    }

    public function test_valid_department_plan_fixture_is_complete(): void
    {
        $plan = $this->chain()['departmentPlan'];

        $this->assertInstanceOf(AnnualPlan::class, $plan->annualPlan);
        $this->assertInstanceOf(Department::class, $plan->department);
        $this->assertSame($plan->annual_plan_id, $plan->annualPlan->id);
        $this->assertSame($plan->department_id, $plan->department->id);
        $this->assertNotEmpty($plan->title);
    }

    public function test_reverse_relationship_department_has_plans(): void
    {
        $context = $this->chain();

        $this->assertTrue(
            $context['department']->departmentPlans->contains($context['departmentPlan']),
        );
        $this->assertTrue(
            $context['department']->departmentTargets->contains($context['departmentTarget']),
        );
    }

    public function test_scopes_draft_active_and_approved(): void
    {
        $context = $this->context();

        $make = fn (string $status) => DepartmentPlan::query()->create([
            'annual_plan_id' => $context['annualPlan']->id,
            'department_id' => $context['department']->id,
            'title' => 'Plan '.$status,
            'status' => $status,
        ]);

        $draft = $make('draft');
        $active = $make('active');
        $approved = $make('approved');

        $this->assertTrue(DepartmentPlan::query()->draft()->pluck('id')->contains($draft->id));
        $this->assertTrue(DepartmentPlan::query()->active()->pluck('id')->contains($active->id));
        $this->assertTrue(DepartmentPlan::query()->approved()->pluck('id')->contains($approved->id));
        $this->assertFalse(DepartmentPlan::query()->active()->pluck('id')->contains($draft->id));
    }

    public function test_soft_delete_uses_trash_bin(): void
    {
        $plan = $this->context()['departmentPlan'];

        $plan->delete();

        $this->assertSoftDeleted('department_plans', ['id' => $plan->id]);
        $this->assertNotNull(DepartmentPlan::withTrashed()->find($plan->id));
    }

    public function test_mass_assignment_guards_protected_columns(): void
    {
        $plan = new DepartmentPlan;

        $this->assertFalse($plan->isFillable('id'));
        $this->assertFalse($plan->isFillable('created_at'));
        $this->assertFalse($plan->isFillable('updated_at'));
        $this->assertFalse($plan->isFillable('deleted_at'));
        $this->assertTrue($plan->isFillable('annual_plan_id'));
        $this->assertTrue($plan->isFillable('created_by'));
    }

    public function test_department_target_and_task_foreign_keys_point_to_plan(): void
    {
        $context = $this->chain();

        $this->assertSame($context['departmentPlan']->id, $context['departmentTarget']->department_plan_id);
        $this->assertSame($context['departmentPlan']->id, $context['task']->department_plan_id);
        $this->assertInstanceOf(DepartmentTarget::class, $context['task']->departmentTarget);
        $this->assertInstanceOf(Task::class, $context['task']);
    }
}
