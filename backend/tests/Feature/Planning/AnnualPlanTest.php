<?php

namespace Tests\Feature\Planning;

use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualPlanTest extends PlanningTestCase
{
    public function test_belongs_to_academic_year_and_department(): void
    {
        $plan = AnnualPlan::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $plan->academicYear());
        $this->assertInstanceOf(AcademicYear::class, $plan->academicYear);
        $this->assertInstanceOf(BelongsTo::class, $plan->department());
        $this->assertInstanceOf(Department::class, $plan->department);
    }

    public function test_belongs_to_creator_and_approver(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $plan = AnnualPlan::factory()->create([
            'created_by' => $creator->id,
            'approved_by' => $approver->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $plan->createdBy());
        $this->assertSame($creator->id, $plan->createdBy->id);
        $this->assertInstanceOf(BelongsTo::class, $plan->approvedBy());
        $this->assertSame($approver->id, $plan->approvedBy->id);
    }

    public function test_has_many_department_plans_employee_targets_and_tasks(): void
    {
        $context = $this->chain();
        $plan = $context['annualPlan'];

        $this->assertInstanceOf(HasMany::class, $plan->departmentPlans());
        $this->assertTrue($plan->departmentPlans->contains($context['departmentPlan']));

        $this->assertInstanceOf(HasMany::class, $plan->employeeTargets());
        $this->assertTrue($plan->employeeTargets->contains($context['employeeTarget']));

        $this->assertInstanceOf(HasMany::class, $plan->tasks());
        $this->assertTrue($plan->tasks->contains($context['task']));
    }

    public function test_approved_at_is_cast_to_datetime(): void
    {
        $plan = AnnualPlan::factory()->create(['approved_at' => now()]);

        $this->assertInstanceOf(Carbon::class, $plan->approved_at);
    }

    public function test_draft_approved_and_active_scopes(): void
    {
        $draft = AnnualPlan::factory()->create(['status' => 'draft']);
        $approved = AnnualPlan::factory()->create(['status' => 'approved']);
        $active = AnnualPlan::factory()->create(['status' => 'active']);

        $this->assertTrue(AnnualPlan::query()->draft()->pluck('id')->contains($draft->id));
        $this->assertTrue(AnnualPlan::query()->approved()->pluck('id')->contains($approved->id));
        $this->assertTrue(AnnualPlan::query()->active()->pluck('id')->contains($active->id));
        $this->assertFalse(AnnualPlan::query()->draft()->pluck('id')->contains($active->id));
    }

    public function test_soft_delete_uses_trash_bin(): void
    {
        $plan = AnnualPlan::factory()->create();

        $plan->delete();

        $this->assertSoftDeleted('annual_plans', ['id' => $plan->id]);
        $this->assertNull(AnnualPlan::query()->find($plan->id));
        $this->assertNotNull(AnnualPlan::withTrashed()->find($plan->id));
    }

    public function test_mass_assignment_guards_protected_columns(): void
    {
        $plan = new AnnualPlan;

        $this->assertFalse($plan->isFillable('id'));
        $this->assertFalse($plan->isFillable('created_at'));
        $this->assertFalse($plan->isFillable('updated_at'));
        $this->assertFalse($plan->isFillable('deleted_at'));
        $this->assertTrue($plan->isFillable('created_by'));
        $this->assertTrue($plan->isFillable('approved_by'));
    }
}
