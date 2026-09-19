<?php

namespace Tests\Feature\Api\Reports;

use App\Enums\Role;
use App\Models\Department;

class ReportAccessTest extends ReportTestCase
{
    public function test_unauthenticated_cannot_access_reports(): void
    {
        $this->getJson('/api/v1/reports/task-completion')->assertUnauthorized();
        $this->getJson('/api/v1/reports/me/performance')->assertUnauthorized();
        $this->getJson('/api/v1/reports/achievements/monthly')->assertUnauthorized();
        $this->getJson('/api/v1/reports/export/task-completion')->assertUnauthorized();
    }

    public function test_employee_cannot_access_manager_only_reports(): void
    {
        $department = Department::factory()->create();
        $this->login($this->employeeUser($department));

        $blocked = [
            '/api/v1/reports/options',
            '/api/v1/reports/task-completion',
            '/api/v1/reports/employees',
            '/api/v1/reports/departments',
            '/api/v1/reports/achievements/monthly',
            '/api/v1/reports/target-vs-actual',
            '/api/v1/reports/trend/monthly-completions',
            '/api/v1/reports/top-performers',
            '/api/v1/reports/export/task-completion',
            '/api/v1/reports/export/employee-performance',
            '/api/v1/reports/export/department-performance',
        ];

        foreach ($blocked as $uri) {
            $this->getJson($uri)->assertForbidden();
        }
    }

    public function test_manager_can_access_report_surfaces(): void
    {
        foreach ([$this->adminUser(), $this->hrUser()] as $user) {
            $this->login($user);

            $this->getJson('/api/v1/reports/options')->assertOk()->assertJsonPath('success', true);
            $this->getJson('/api/v1/reports/task-completion')->assertOk()->assertJsonPath('success', true);
            $this->getJson('/api/v1/reports/employees')->assertOk()->assertJsonPath('success', true);
            $this->getJson('/api/v1/reports/departments')->assertOk()->assertJsonPath('success', true);
            $this->getJson('/api/v1/reports/achievements/monthly')->assertOk();
            $this->getJson('/api/v1/reports/target-vs-actual')->assertOk();
            $this->getJson('/api/v1/reports/trend/monthly-completions')->assertOk();
            $this->getJson('/api/v1/reports/top-performers')->assertOk();
        }
    }

    public function test_employee_can_view_own_performance(): void
    {
        $department = Department::factory()->create();
        [$user, $employee] = $this->employeeAndUser($department);
        $this->login($user);

        $this->getJson('/api/v1/reports/me/performance')
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('meta.report_type', 'employee-performance');

        $this->getJson("/api/v1/reports/achievements/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id);
    }

    public function test_employee_cannot_view_another_employees_achievements(): void
    {
        $department = Department::factory()->create();
        $me = $this->employee($department);
        $other = $this->employee($department);

        $user = $this->roleUser(Role::Employee);
        $me->update(['user_id' => $user->id]);
        $this->login($user);

        $this->getJson("/api/v1/reports/achievements/employees/{$other->id}")->assertForbidden();
    }

    public function test_employee_without_profile_gets_404_on_own_performance(): void
    {
        $this->login($this->employeeUser(null, false));

        $this->getJson('/api/v1/reports/me/performance')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_employee_export_forced_to_own_record(): void
    {
        $department = Department::factory()->create();
        [$user, $employee] = $this->employeeAndUser($department);
        $other = $this->employee($department);
        $this->login($user);

        $csv = $this->get("/api/v1/reports/export/achievements?employee_id={$other->id}&format=csv")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($employee->fullName, $csv);
        $this->assertStringNotContainsString($other->fullName, $csv);
    }

    public function test_department_head_is_locked_to_own_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $head = $this->departmentHeadUser($deptA);
        $empB = $this->employee($deptB);
        $this->login($head);

        $this->getJson("/api/v1/reports/task-completion?department_id={$deptB->id}")->assertForbidden();
        $this->getJson("/api/v1/reports/departments/{$deptB->id}")->assertForbidden();
        $this->getJson("/api/v1/reports/employees/{$empB->id}")->assertForbidden();
        $this->getJson("/api/v1/reports/achievements/employees/{$empB->id}")->assertForbidden();
        $this->getJson("/api/v1/reports/export/achievements?department_id={$deptB->id}")->assertForbidden();
    }

    public function test_department_head_scope_filters_data(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $head = $this->departmentHeadUser($deptA);
        $empA = $this->employee($deptA);
        $empB = $this->employee($deptB);

        $this->taskIn($deptB, ['status' => 'in_progress']);

        $this->login($head);

        $rows = $this->getJson('/api/v1/reports/employees')->assertOk()->json('data');

        $names = array_column($rows, 'employee_name');
        $this->assertContains($empA->fullName, $names);
        $this->assertNotContains($empB->fullName, $names);

        $taskReport = $this->getJson('/api/v1/reports/task-completion')->assertOk()->json('data');
        $this->assertEquals(0, $taskReport['total_tasks']);

        $options = $this->getJson('/api/v1/reports/options')->assertOk()->json('data');
        $this->assertCount(1, $options['departments']);
        $this->assertEquals($deptA->id, $options['departments'][0]['id']);
    }

    public function test_department_head_can_view_own_department_surface(): void
    {
        $deptA = Department::factory()->create();
        $head = $this->departmentHeadUser($deptA);
        $this->login($head);

        $this->getJson('/api/v1/reports/task-completion')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/reports/trend/monthly-completions')->assertOk()->assertJsonPath('success', true);
    }
}