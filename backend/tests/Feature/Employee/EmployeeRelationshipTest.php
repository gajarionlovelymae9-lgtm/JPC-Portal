<?php

namespace Tests\Feature\Employee;

use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_employee_is_one_to_one(): void
    {
        $user = User::query()->where('email', 'admin@jpc.local')->firstOrFail();

        $this->assertNotNull($user->employee);
        $this->assertSame('DEV-0001', $user->employee->employee_number);
        $this->assertSame($user->id, $user->employee->user->id);
    }

    public function test_employee_belongs_to_department_and_position(): void
    {
        $employee = Employee::query()->where('employee_number', 'DEV-0008')->firstOrFail();

        $this->assertInstanceOf(Department::class, $employee->department);
        $this->assertSame('CBA', $employee->department->code);
        $this->assertInstanceOf(Position::class, $employee->position);
        $this->assertSame('PROFESSOR', $employee->position->code);
    }

    public function test_supervisor_and_subordinates_resolve(): void
    {
        $head = Employee::query()->where('employee_number', 'DEV-0015')->firstOrFail();
        $member = Employee::query()->where('employee_number', 'DEV-0004')->firstOrFail();

        $this->assertSame(3, $head->subordinates()->count());
        $this->assertSame('DEV-0015', $member->supervisor->employee_number);
        $this->assertEmpty($head->subordinates()->where('department_id', '!=', $head->department_id)->get());
    }

    public function test_histories_resolve_with_change_log_columns(): void
    {
        $employee = Employee::query()->where('employee_number', 'DEV-0006')->firstOrFail();
        $history = $employee->histories()->first();

        $this->assertNotNull($history);
        $this->assertSame($employee->id, $history->employee_id);
        $this->assertNotNull($history->field_name);
        $this->assertNotNull($history->old_value);
        $this->assertNotNull($history->new_value);
        $this->assertNotNull($history->effective_date);
        $this->assertNotNull($history->reason);
        $this->assertNotNull($history->changed_by);
        $this->assertNotNull($history->changedBy);
    }

    public function test_targets_resolve_to_correct_employee(): void
    {
        $employee = Employee::query()->where('employee_number', 'DEV-0004')->firstOrFail();

        $this->assertGreaterThanOrEqual(2, $employee->employeeTargets()->count());
        foreach ($employee->employeeTargets as $target) {
            $this->assertSame($employee->id, $target->employee_id);
            $this->assertNotNull($target->annualPlan);
        }
    }

    public function test_performance_records_resolve_with_valid_periods(): void
    {
        $employee = Employee::query()->where('employee_number', 'DEV-0004')->firstOrFail();

        $records = $employee->performanceRecords()->get();
        $this->assertCount(3, $records);

        $periods = $records->pluck('period_start')->map(fn ($d) => $d->format('Y-m'))->all();
        $this->assertSame(['2026-06', '2026-07', '2026-08'], $periods);

        foreach ($records as $record) {
            $this->assertLessThanOrEqual($record->period_end, $record->period_start);
        }
    }

    public function test_reverse_relationships_resolve(): void
    {
        $education = Department::query()->where('code', 'EDU')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $education->employees()->count());

        $headPosition = Position::query()->where('code', 'DEPT_HEAD')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $headPosition->employees()->count());

        $plan = AnnualPlan::query()->first();
        $this->assertNotNull($plan);
        $this->assertGreaterThanOrEqual(1, $plan->employeeTargets()->count());
    }
}
