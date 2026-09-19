<?php

namespace Tests\Feature\Database;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_default_active_regular_employee(): void
    {
        $employee = Employee::factory()->create();

        $this->assertNotNull($employee->employee_number);
        $this->assertSame('active', $employee->employment_status);
        $this->assertSame('regular', $employee->employment_type);
        $this->assertNull($employee->user_id);
        $this->assertNull($employee->supervisor_id);
        $this->assertNull($employee->date_ended);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'employment_status' => 'active', 'employment_type' => 'regular']);
    }

    public function test_factory_creates_related_department_and_position(): void
    {
        $employee = Employee::factory()->create();

        $this->assertInstanceOf(Department::class, $employee->department);
        $this->assertInstanceOf(Position::class, $employee->position);
        $this->assertDatabaseHas('departments', ['id' => $employee->department_id]);
        $this->assertDatabaseHas('positions', ['id' => $employee->position_id]);
    }

    public function test_employment_status_states(): void
    {
        $this->assertSame('active', Employee::factory()->active()->create()->employment_status);

        $inactive = Employee::factory()->inactive()->create();
        $this->assertSame('inactive', $inactive->employment_status);
        $this->assertNotNull($inactive->date_ended);

        $onLeave = Employee::factory()->onLeave()->create();
        $this->assertSame('on_leave', $onLeave->employment_status);
        $this->assertNull($onLeave->date_ended);
    }

    public function test_employment_type_states(): void
    {
        $this->assertSame('regular', Employee::factory()->regular()->create()->employment_type);
        $this->assertSame('probationary', Employee::factory()->probationary()->create()->employment_type);
    }

    public function test_factory_generates_unique_employee_numbers(): void
    {
        $first = Employee::factory()->create();
        $second = Employee::factory()->create();

        $this->assertNotSame($first->employee_number, $second->employee_number);
    }
}
