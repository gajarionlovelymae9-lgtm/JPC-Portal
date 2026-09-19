<?php

namespace Tests\Feature\Database;

use App\Models\DepartmentHead;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentEmployeeDatasetTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_employee_dataset_shapes_correctly(): void
    {
        $this->seed();

        $this->assertSame(15, User::query()->count());
        $this->assertSame(15, Employee::query()->count());
        $this->assertSame(2, DepartmentHead::query()->where('is_current', true)->count());

        $this->assertGreaterThanOrEqual(20, EmployeeTarget::query()->count());
        $this->assertGreaterThanOrEqual(10, EmployeeHistory::query()->count());

        $this->assertSame(45, EmployeePerformanceRecord::query()->count());

        $this->assertSame(0, Employee::query()->whereNull('user_id')->count());

        foreach (['active', 'on_leave', 'inactive'] as $status) {
            $this->assertGreaterThanOrEqual(1, Employee::query()->where('employment_status', $status)->count());
        }

        foreach (['regular', 'probationary', 'contractual'] as $type) {
            $this->assertGreaterThanOrEqual(1, Employee::query()->where('employment_type', $type)->count());
        }
    }

    public function test_supervisor_hierarchies_follow_expected_heads(): void
    {
        $this->seed();

        $educationHead = Employee::query()->where('employee_number', 'DEV-0015')->firstOrFail();
        $citHead = Employee::query()->where('employee_number', 'DEV-0003')->firstOrFail();

        $this->assertSame(3, $educationHead->subordinates()->count());
        $this->assertSame(2, $citHead->subordinates()->count());

        $this->assertNull($educationHead->supervisor_id);
        $this->assertNull($citHead->supervisor_id);

        $this->assertNull(Employee::query()->where('employee_number', 'DEV-0001')->first()->supervisor_id);

        $this->assertSame(
            ['DEV-0004', 'DEV-0005', 'DEV-0006'],
            $educationHead->subordinates()->orderBy('employee_number')->pluck('employee_number')->all(),
        );
    }

    public function test_history_records_point_to_existing_employees_and_users(): void
    {
        $this->seed();

        $histories = EmployeeHistory::with(['employee', 'changedBy'])->get();
        $this->assertCount(13, $histories);

        foreach ($histories as $history) {
            $this->assertNotNull($history->employee);
            $this->assertNotNull($history->changed_by);
            $this->assertNotNull($history->changedBy);
        }
    }

    public function test_development_seeders_are_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(15, User::query()->count());
        $this->assertSame(15, Employee::query()->count());
        $this->assertSame(13, EmployeeHistory::query()->count());
        $this->assertSame(2, DepartmentHead::query()->where('is_current', true)->count());
    }
}
