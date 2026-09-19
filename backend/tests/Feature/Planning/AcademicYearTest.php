<?php

namespace Tests\Feature\Planning;

use App\Models\AcademicYear;
use App\Models\AnnualPlan;
use App\Models\EmployeeTarget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AcademicYearTest extends PlanningTestCase
{
    public function test_annual_plans_relationship(): void
    {
        $context = $this->context();

        $this->assertInstanceOf(HasMany::class, $context['academicYear']->annualPlans());
        $this->assertTrue(
            $context['academicYear']->annualPlans->contains($context['annualPlan']),
        );
    }

    public function test_employee_targets_relationship(): void
    {
        $context = $this->context();

        $this->assertInstanceOf(HasManyThrough::class, $context['academicYear']->employeeTargets());
        $this->assertTrue(
            $context['academicYear']->employeeTargets->contains($context['employeeTarget']),
        );
    }

    public function test_date_casts_are_carbon_instances(): void
    {
        $year = AcademicYear::factory()->create();

        $this->assertInstanceOf(Carbon::class, $year->start_date);
        $this->assertInstanceOf(Carbon::class, $year->end_date);
    }

    public function test_active_and_closed_scopes(): void
    {
        $active = AcademicYear::factory()->create(['status' => 'active']);
        $closed = AcademicYear::factory()->create(['status' => 'closed']);

        $this->assertTrue(AcademicYear::query()->active()->pluck('id')->contains($active->id));
        $this->assertFalse(AcademicYear::query()->active()->pluck('id')->contains($closed->id));
        $this->assertTrue(AcademicYear::query()->closed()->pluck('id')->contains($closed->id));
    }

    public function test_mass_assignment_guards_primary_and_timestamp_keys(): void
    {
        $year = new AcademicYear;

        $this->assertFalse($year->isFillable('id'));
        $this->assertFalse($year->isFillable('created_at'));
        $this->assertFalse($year->isFillable('updated_at'));
        $this->assertTrue($year->isFillable('name'));
    }

    public function test_annual_plan_belongs_back_to_year(): void
    {
        $plan = AnnualPlan::factory()->create();

        $this->assertInstanceOf(AcademicYear::class, $plan->academicYear);
        $this->assertSame($plan->academic_year_id, $plan->academicYear->id);
    }

    public function test_employee_target_belongs_to_year_through_plan(): void
    {
        $context = $this->context();
        $target = $context['employeeTarget'];

        $this->assertInstanceOf(EmployeeTarget::class, $target);
        $this->assertSame($context['academicYear']->id, $target->annualPlan->academicYear->id);
    }
}
