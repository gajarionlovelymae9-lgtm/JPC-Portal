<?php

namespace Tests\Feature\Employee;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeePerformanceRecord;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_be_created(): void
    {
        $employee = Employee::factory()->create();

        $this->assertNotNull($employee->employee_number);
        $this->assertNotNull($employee->department_id);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_factory_creates_ten_valid_employees(): void
    {
        Employee::factory()->count(10)->create();

        $this->assertSame(10, Employee::query()->count());
        $this->assertSame(10, Employee::query()->whereNotNull('employee_number')->count());
        $this->assertSame(10, Employee::query()->whereNotNull('department_id')->count());
        $this->assertSame(10, Employee::query()->distinct()->count('employee_number'));
    }

    public function test_soft_delete_hides_employee_and_preserves_history(): void
    {
        $employee = Employee::factory()->create();
        $employee->histories()->create([
            'field_name' => 'employment_status',
            'old_value' => 'active',
            'new_value' => 'inactive',
            'effective_date' => now()->toDateString(),
            'reason' => 'row-removed check',
        ]);

        $employee->delete();

        $this->assertNull(Employee::query()->find($employee->id));
        $this->assertNotNull(Employee::withTrashed()->find($employee->id));
        $this->assertSame(1, EmployeeHistory::query()->count());

        $employee->restore();
        $this->assertNotNull(Employee::query()->find($employee->id));
        $this->assertSame(1, EmployeeHistory::query()->count());
    }

    public function test_mass_assignment_protects_identity_columns(): void
    {
        $department = Department::factory()->create();

        $created = Employee::query()->create([
            'id' => 12345,
            'employee_number' => 'FCT-PROTECT-1',
            'first_name' => 'Guard',
            'last_name' => 'Check',
            'department_id' => $department->id,
        ]);

        $this->assertNotSame(12345, $created->id);
        $this->assertSame('FCT-PROTECT-1', $created->employee_number);

        $fillable = (new Employee)->getFillable();
        foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $ignored) {
            $this->assertNotContains($ignored, $fillable);
        }
    }

    public function test_performance_verdict_fields_cannot_be_mass_assigned(): void
    {
        $employee = Employee::factory()->create();
        $academicYear = AcademicYear::factory()->create();

        $record = EmployeePerformanceRecord::query()->create([
            'employee_id' => $employee->id,
            'academic_year_id' => $academicYear->id,
            'period_type' => 'monthly',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'overall_score' => 99.99,
            'performance_status' => 'improving',
        ]);

        $this->assertNull($record->overall_score);
        $this->assertNull($record->performance_status);
        $this->assertDatabaseHas('employee_performance_records', [
            'id' => $record->id,
            'overall_score' => null,
        ]);
        $this->assertDatabaseMissing('employee_performance_records', [
            'id' => $record->id,
            'overall_score' => '99.99',
            'performance_status' => 'improving',
        ]);
    }

    public function test_common_dashboard_queries_avoid_n_plus_one(): void
    {
        $department = Department::factory()->create();
        $position = Position::factory()->create();

        Employee::factory()->count(15)->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $employees = Employee::with(['department', 'position'])->get();

        $this->assertCount(15, $employees);
        $this->assertLessThanOrEqual(5, $queries);
    }
}
