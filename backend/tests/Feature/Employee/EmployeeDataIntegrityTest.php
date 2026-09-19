<?php

namespace Tests\Feature\Employee;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeTarget;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_employee_number_is_rejected(): void
    {
        $employee = Employee::factory()->create(['employee_number' => 'JPC-EMP-0001']);

        $this->expectException(QueryException::class);

        Employee::factory()->create(['employee_number' => 'JPC-EMP-0001']);
        $this->assertSame(1, Employee::query()->where('employee_number', 'JPC-EMP-0001')->count());
    }

    public function test_duplicate_user_assignment_is_rejected(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        Employee::factory()->create(['user_id' => $user->id]);
        $this->assertSame(1, Employee::query()->where('user_id', $user->id)->count());
    }

    public function test_optional_fields_can_be_null(): void
    {
        $employee = Employee::factory()->create([
            'middle_name' => null,
            'suffix' => null,
            'date_of_birth' => null,
            'phone' => null,
            'address' => null,
            'supervisor_id' => null,
            'profile_photo_path' => null,
        ]);

        $fresh = Employee::query()->findOrFail($employee->id);
        $this->assertNull($fresh->middle_name);
        $this->assertNull($fresh->suffix);
        $this->assertNull($fresh->date_of_birth);
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->address);
        $this->assertNull($fresh->supervisor_id);
        $this->assertNull($fresh->profile_photo_path);
    }

    public function test_decimal_scores_are_preserved(): void
    {
        $record = EmployeePerformanceRecord::factory()->create(['completion_rate' => 95.50]);

        $stored = DB::table('employee_performance_records')
            ->where('id', $record->id)
            ->value('completion_rate');

        $this->assertSame(95.50, (float) $stored);
        $this->assertSame('95.50', $record->fresh()->completion_rate);
    }

    public function test_seeded_relationships_have_no_orphans_or_cross_department_supervisors(): void
    {
        $this->seed();

        $this->assertSame(0, Employee::query()->whereDoesntHave('department')->count());
        $this->assertSame(0, Employee::query()->whereDoesntHave('position')->count());
        $this->assertSame(0, Employee::query()->whereNotNull('user_id')->whereDoesntHave('user')->count());
        $this->assertSame(0, Employee::query()->whereNotNull('supervisor_id')->whereDoesntHave('supervisor')->count());
        $this->assertSame(0, EmployeeHistory::query()->whereDoesntHave('employee')->count());
        $this->assertSame(0, EmployeeTarget::query()->whereDoesntHave('employee')->count());
        $this->assertSame(0, EmployeeTarget::query()->whereDoesntHave('annualPlan')->count());
        $this->assertSame(0, EmployeePerformanceRecord::query()->whereDoesntHave('employee')->count());
        $this->assertSame(0, EmployeePerformanceRecord::query()->whereDoesntHave('academicYear')->count());

        $crossDepartment = Employee::query()
            ->whereNotNull('supervisor_id')
            ->whereDoesntHave('supervisor', fn ($q) => $q->whereColumn('department_id', 'employees.department_id'))
            ->get();

        $knownException = $crossDepartment->count() === 1
            && $crossDepartment->first()->employee_number === 'DEV-0002'
            && $crossDepartment->first()->supervisor_id === DB::table('employees')
                ->where('employee_number', 'DEV-0001')
                ->value('id');

        $this->assertTrue(
            $knownException,
            'Only the documented cross-department supervisor (DEV-0002 → DEV-0001) is permitted'
        );
    }

    public function test_indexes_exist_for_common_filters(): void
    {
        $this->assertIndexes('employees', [
            'employees_department_id_index',
            'employees_position_id_index',
            'employees_employment_status_index',
            'idx_employees_employment_type',
            'idx_employees_supervisor_id',
            'idx_employees_date_hired',
            'idx_employees_name',
            'employees_user_id_unique',
            'employees_employee_number_unique',
        ]);

        $this->assertIndexes('employee_targets', [
            'employee_targets_employee_id_index',
            'employee_targets_status_index',
        ]);

        $this->assertIndexes('employee_performance_records', [
            'uq_employee_performance_records_period',
            'employee_performance_records_academic_year_id_index',
            'employee_performance_records_period_start_index',
        ]);
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertIndexes(string $table, array $expected): void
    {
        $database = DB::connection()->getDatabaseName();

        $actual = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->distinct()
            ->pluck('index_name')
            ->all();

        foreach ($expected as $index) {
            $this->assertContains($index, $actual, "Missing index {$index} on {$table}");
        }
    }
}
