<?php

namespace Tests\Feature\Employee;

use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeePerformanceRecord;
use App\Models\EmployeeTarget;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\DevelopmentEmployeeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_dataset_meets_target_counts_roles_and_domains(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(15, User::query()->count());
        $this->assertSame(15, Employee::query()->count());
        $this->assertSame(6, Department::query()->count());
        $this->assertGreaterThanOrEqual(8, Position::query()->count());
        $this->assertGreaterThanOrEqual(10, EmployeeHistory::query()->count());
        $this->assertGreaterThanOrEqual(20, EmployeeTarget::query()->count());
        $this->assertGreaterThanOrEqual(30, EmployeePerformanceRecord::query()->count());
        $this->assertSame(2, DepartmentHead::query()->where('is_current', true)->count());

        $roleByEmail = [
            'admin@jpc.local' => 'admin',
            'hr@jpc.local' => 'hr',
            'departmenthead@jpc.local' => 'department_head',
            'employee@jpc.local' => 'employee',
        ];
        foreach ($roleByEmail as $email => $roleName) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame($roleName, $user->role->name);
        }

        $this->assertSame(0, User::query()->whereNot('email', 'like', '%@jpc.local')->count());
        $this->assertSame(0, Employee::query()->whereNull('user_id')->count());
    }

    public function test_department_head_hierarchy_is_scoped_to_their_department(): void
    {
        $this->seed();

        $head = Employee::query()->where('employee_number', 'DEV-0015')->firstOrFail();

        $subordinates = $head->subordinates()->orderBy('employee_number')->pluck('employee_number')->all();
        $this->assertSame(['DEV-0004', 'DEV-0005', 'DEV-0006'], $subordinates);
        $this->assertSame(0, $head->subordinates()->where('department_id', '!=', $head->department_id)->count());
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(15, User::query()->count());
        $this->assertSame(15, Employee::query()->count());
        $this->assertSame(13, EmployeeHistory::query()->count());
        $this->assertSame(2, DepartmentHead::query()->where('is_current', true)->count());
    }

    public function test_development_seeders_respect_the_environment_gate(): void
    {
        config(['jpc.seed_development.enabled' => false]);

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(PositionSeeder::class);

        (new DevelopmentEmployeeSeeder)->run();

        $this->assertSame(0, Employee::query()->count());
    }
}
