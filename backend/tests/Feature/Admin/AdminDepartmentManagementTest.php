<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * People Management module — department management surface.
 *
 * Covers the consolidated Department Management writes: create, edit,
 * activate/deactivate (never delete), head assignment with the strict
 * membership+role rules, and employee transfer with history preservation.
 */
class AdminDepartmentManagementTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->loginAs($this->adminUser());
    }

    protected function department(array $attributes = []): Department
    {
        return Department::factory()->create($attributes);
    }

    /* ------------------------------------------------------------------ */
    /* Create / edit */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function admin_can_create_a_department(): void
    {
        $response = $this->postJson('/api/v1/admin/departments', [
            'name' => 'Quality Assurance',
            'code' => 'QA',
            'description' => 'Institutional QA.',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Quality Assurance');

        $this->assertDatabaseHas('departments', ['name' => 'Quality Assurance', 'code' => 'QA']);
    }

    #[Test]
    public function admin_can_create_a_department_with_a_full_academic_program_name(): void
    {
        $name = 'Bachelor of Science in Civil Engineering';

        $this->postJson('/api/v1/admin/departments', [
            'name' => $name,
            'code' => 'BSCE',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.code', 'BSCE');

        $this->assertDatabaseHas('departments', ['name' => $name, 'code' => 'BSCE']);
    }

    #[Test]
    public function department_names_allow_common_punctuation_and_an_omitted_code(): void
    {
        $name = "College of Arts & Sciences (O'Brien Hall) - Main Campus";

        $this->postJson('/api/v1/admin/departments', ['name' => $name])
            ->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.code', null);

        $this->assertDatabaseHas('departments', ['name' => $name, 'code' => null]);
    }

    #[Test]
    public function department_names_accept_the_database_limit_and_reject_longer_values(): void
    {
        $this->postJson('/api/v1/admin/departments', ['name' => str_repeat('A', 255)])
            ->assertCreated();

        $this->assertDatabaseHas('departments', ['name' => str_repeat('A', 255)]);

        $this->postJson('/api/v1/admin/departments', ['name' => str_repeat('B', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name')
            ->assertJsonPath('errors.name.0', 'The department name may not be longer than 255 characters.');
    }

    #[Test]
    public function duplicate_department_name_is_rejected(): void
    {
        $this->department(['name' => 'Finance', 'code' => 'FIN']);

        $this->postJson('/api/v1/admin/departments', ['name' => 'Finance'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name')
            ->assertJsonPath('errors.name.0', 'A department with this name already exists.');
    }

    #[Test]
    public function duplicate_department_code_is_rejected_with_a_clear_message(): void
    {
        $this->department(['name' => 'Civil Engineering', 'code' => 'BSCE']);

        $this->postJson('/api/v1/admin/departments', [
            'name' => 'Computer Engineering',
            'code' => 'BSCE',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code')
            ->assertJsonPath('errors.code.0', 'A department with this code already exists.');
    }

    #[Test]
    public function admin_can_update_to_a_long_punctuated_name_without_a_code(): void
    {
        $department = $this->department(['name' => 'Old Name', 'code' => 'OLD']);
        $name = 'Bachelor of Science in Information Technology (Network Administration)';

        $this->putJson("/api/v1/admin/departments/{$department->id}", [
            'name' => $name,
            'code' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.code', null);

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => $name, 'code' => null]);
    }

    #[Test]
    public function admin_can_update_a_department(): void
    {
        $department = $this->department(['name' => 'Old Name']);

        $this->putJson("/api/v1/admin/departments/{$department->id}", [
            'name' => 'New Name',
            'description' => 'Updated.',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'New Name']);
    }

    #[Test]
    public function admin_cannot_hard_delete_a_department(): void
    {
        $department = $this->department();

        // No destructive route exists — DELETE must not resolve.
        $this->deleteJson("/api/v1/admin/departments/{$department->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    /* ------------------------------------------------------------------ */
    /* Status */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function departments_are_deactivated_not_deleted(): void
    {
        $department = $this->department(['status' => 'active']);

        $this->postJson("/api/v1/admin/departments/{$department->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'status' => 'inactive']);
        $this->assertSame('inactive', $department->refresh()->status);
    }

    #[Test]
    public function same_status_transition_is_rejected(): void
    {
        $department = $this->department(['status' => 'active']);

        $this->postJson("/api/v1/admin/departments/{$department->id}/status", ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /* ------------------------------------------------------------------ */
    /* Head assignment */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function head_must_belong_to_the_department(): void
    {
        $department = $this->department();
        $other = $this->department();

        $headUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $employee = $this->employeeUser($other, $headUser);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", [
            'employee_id' => $employee->id,
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->assertDatabaseMissing('department_heads', ['department_id' => $department->id]);
    }

    #[Test]
    public function head_must_already_hold_the_department_head_role(): void
    {
        $department = $this->department();

        // Employee has an account but with the plain employee role.
        $employeeUser = $this->roleUser(Role::Employee);
        $employee = $this->employeeUser($department, $employeeUser);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", [
            'employee_id' => $employee->id,
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        // The role is never granted implicitly.
        $this->assertSame(
            Role::Employee->value,
            $employeeUser->refresh()->role->name,
        );
    }

    #[Test]
    public function head_requires_a_login_account(): void
    {
        $department = $this->department();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", [
            'employee_id' => $employee->id,
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    #[Test]
    public function admin_can_assign_a_valid_department_head(): void
    {
        $department = $this->department();
        $headUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $employee = $this->employeeUser($department, $headUser);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", [
            'employee_id' => $employee->id,
        ])->assertOk()->assertJsonPath('data.head.employee_id', $employee->id);

        $this->assertDatabaseHas('department_heads', [
            'department_id' => $department->id,
            'employee_id' => $employee->id,
            'is_current' => true,
        ]);
    }

    #[Test]
    public function reassigning_a_head_closes_the_previous_record(): void
    {
        $department = $this->department();

        $firstHeadUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $firstHead = $this->employeeUser($department, $firstHeadUser);

        $secondHeadUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $secondHead = $this->employeeUser($department, $secondHeadUser);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $firstHead->id]);
        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $secondHead->id])
            ->assertOk();

        $this->assertDatabaseHas('department_heads', [
            'employee_id' => $firstHead->id,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('department_heads', [
            'employee_id' => $secondHead->id,
            'is_current' => true,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Eligible head discovery */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function eligible_heads_endpoint_returns_only_qualified_employees_of_the_department(): void
    {
        $department = $this->department();
        $other = $this->department();

        $headUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = $this->employeeUser($department, $headUser);

        // Excluded: head role but in another department.
        $otherHeadUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $this->employeeUser($other, $otherHeadUser);

        // Excluded: in the department but without the head role.
        $this->employeeUser($department, $this->roleUser(Role::Employee));

        $this->getJson("/api/v1/admin/departments/{$department->id}/eligible-heads")
            ->assertOk()
            ->assertJsonPath('data.department.id', $department->id)
            ->assertJsonPath('data.employees.0.id', $head->id)
            ->assertJsonPath('data.employees.0.employee_number', $head->employee_number)
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.diagnostics.eligible', 1)
            ->assertJsonPath('data.diagnostics.active_employees', 2)
            ->assertJsonPath('data.diagnostics.without_head_role', 1);
    }

    #[Test]
    public function eligible_heads_endpoint_explains_why_a_picker_is_empty(): void
    {
        $department = $this->department();
        $other = $this->department();

        // Active employee without an account.
        Employee::factory()->create(['department_id' => $department->id, 'user_id' => null]);

        // Active employee with an inactive head-role account.
        $inactiveHeadUser = User::factory()->create([
            'role_id' => $this->roleId(Role::DepartmentHead),
            'status' => UserStatus::Inactive,
        ]);
        $this->employeeUser($department, $inactiveHeadUser);

        // Not counted: employee in another department.
        $this->departmentHeadUser($other);

        $this->getJson("/api/v1/admin/departments/{$department->id}/eligible-heads")
            ->assertOk()
            ->assertJsonCount(0, 'data.employees')
            ->assertJsonPath('data.diagnostics.total_employees', 2)
            ->assertJsonPath('data.diagnostics.active_employees', 2)
            ->assertJsonPath('data.diagnostics.without_account', 1)
            ->assertJsonPath('data.diagnostics.inactive_account', 1)
            ->assertJsonPath('data.diagnostics.eligible', 0);
    }

    #[Test]
    public function head_role_granted_through_the_role_user_pivot_is_accepted(): void
    {
        $department = $this->department();

        $user = $this->roleUser(Role::Employee);
        $user->roles()->attach($this->roleId(Role::DepartmentHead));
        $employee = $this->employeeUser($department, $user);

        $this->getJson("/api/v1/admin/departments/{$department->id}/eligible-heads")
            ->assertOk()
            ->assertJsonPath('data.employees.0.id', $employee->id);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $employee->id])
            ->assertOk()
            ->assertJsonPath('data.head.employee_id', $employee->id);

        // The legacy role column is untouched — the pivot grant is enough and no
        // role is written by the assignment.
        $this->assertSame(Role::Employee->value, $user->refresh()->role->name);
    }

    #[Test]
    public function head_with_an_inactive_account_is_rejected(): void
    {
        $department = $this->department();

        $user = User::factory()->create([
            'role_id' => $this->roleId(Role::DepartmentHead),
            'status' => UserStatus::Inactive,
        ]);
        $employee = $this->employeeUser($department, $user);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $employee->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');

        $this->assertDatabaseMissing('department_heads', ['department_id' => $department->id]);
    }

    #[Test]
    public function assigning_the_current_head_again_is_rejected_without_overwriting_history(): void
    {
        $department = $this->department();
        $headUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = $this->employeeUser($department, $headUser);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $head->id])
            ->assertOk();

        $original = DepartmentHead::query()->where('employee_id', $head->id)->firstOrFail();

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $head->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');

        $this->assertSame(1, DepartmentHead::query()->where('employee_id', $head->id)->count());
        $this->assertSame($original->id, DepartmentHead::query()->where('employee_id', $head->id)->value('id'));
        $this->assertTrue((bool) $original->refresh()->is_current);
    }

    #[Test]
    public function reassigning_after_a_prior_term_preserves_the_old_row_as_history(): void
    {
        $department = $this->department();
        $headUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = $this->employeeUser($department, $headUser);

        $previous = DepartmentHead::create([
            'department_id' => $department->id,
            'employee_id' => $head->id,
            'start_date' => today()->subYear(),
            'end_date' => today()->subMonths(2),
            'is_current' => false,
        ]);

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $head->id])
            ->assertOk();

        $previous->refresh();
        $this->assertSame(today()->subMonths(2)->toDateString(), $previous->end_date?->toDateString());
        $this->assertFalse((bool) $previous->is_current);

        $current = DepartmentHead::query()
            ->where('employee_id', $head->id)
            ->where('is_current', true)
            ->firstOrFail();
        $this->assertNotSame($previous->id, $current->id);
        $this->assertSame(today()->toDateString(), $current->start_date?->toDateString());
    }

    #[Test]
    public function non_admins_cannot_discover_or_assign_department_heads(): void
    {
        $department = $this->department();
        $headUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = $this->employeeUser($department, $headUser);

        $this->loginAs($this->roleUser(Role::Employee));

        $this->getJson("/api/v1/admin/departments/{$department->id}/eligible-heads")
            ->assertForbidden();

        $this->postJson("/api/v1/admin/departments/{$department->id}/head", ['employee_id' => $head->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('department_heads', ['department_id' => $department->id]);
    }

    /* ------------------------------------------------------------------ */
    /* Employee transfer */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function admin_can_transfer_employees_into_a_department(): void
    {
        $department = $this->department();
        $source = $this->department();
        $employee = $this->employeeUser($source);

        $this->postJson("/api/v1/admin/departments/{$department->id}/employees", [
            'employee_ids' => [$employee->id],
        ])->assertOk()->assertJsonPath('data.id', $department->id);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $department->id,
        ]);
    }

    #[Test]
    public function transferred_department_heads_are_unassigned_from_the_old_department(): void
    {
        $old = $this->department();
        $new = $this->department();

        $headUser = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = $this->employeeUser($old, $headUser);
        DepartmentHead::create([
            'department_id' => $old->id,
            'employee_id' => $head->id,
            'start_date' => today(),
            'is_current' => true,
        ]);

        $this->postJson("/api/v1/admin/departments/{$new->id}/employees", [
            'employee_ids' => [$head->id],
        ])->assertOk();

        $this->assertDatabaseHas('department_heads', [
            'employee_id' => $head->id,
            'is_current' => false,
        ]);
    }

    #[Test]
    public function inactive_departments_cannot_receive_transfers(): void
    {
        $department = $this->department(['status' => 'inactive']);
        $employee = $this->employeeUser();

        $this->postJson("/api/v1/admin/departments/{$department->id}/employees", [
            'employee_ids' => [$employee->id],
        ])->assertStatus(422)->assertJsonValidationErrors('department');
    }

    /* ------------------------------------------------------------------ */
    /* Summary */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function summary_counts_come_from_live_queries(): void
    {
        $this->department(['status' => 'active']);
        $this->department(['status' => 'inactive']);

        $this->getJson('/api/v1/admin/departments/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.active', 1)
            ->assertJsonPath('data.inactive', 1);
    }
}
