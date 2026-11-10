<?php

namespace Tests\Feature\Api\Hr;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\Position;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for HR employee *management*.
 *
 * The read-only monitoring surface is covered by HrDashboardApiTest. What is
 * pinned here is the half that writes:
 *
 *  1. Authorization — the route middleware and EmployeePolicy must both hold,
 *     and an HR user without `employees.create`/`employees.update` is refused.
 *  2. Honesty on read-back — a created employee comes back with a real profile,
 *     and rates are `null` rather than 0 when there is nothing to divide.
 *  3. Audit — every write lands in `activity_logs`, and every changed field in
 *     `employee_histories`, with no row written for a no-op edit.
 */
class HrEmployeeManagementApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private User $hr;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
        $this->hr = $this->hrUser();
        $this->department = Department::factory()->create(['name' => 'College of Nursing']);
        $this->loginAs($this->hr);
    }

    /* ------------------------------------------------------------------ */
    /* Shape of the registered surface */
    /* ------------------------------------------------------------------ */

    /**
     * The registered verbs, pinned by route *template* rather than by a literal
     * id, so the assertion survives a different primary key.
     */
    public function test_employee_management_routes_are_exactly_these_methods(): void
    {
        $this->app['router']->getRoutes()->refreshNameLookups();

        $methods = function (string $uri): array {
            return collect($this->app['router']->getRoutes()->getRoutes())
                ->filter(fn ($route) => $route->uri() === ltrim($uri, '/'))
                ->flatMap(fn ($route) => $route->methods())
                ->unique()
                ->sort()
                ->values()
                ->all();
        };

        // The directory stays readable; management adds exactly POST.
        $this->assertSame(['GET', 'HEAD', 'POST'], $methods('/api/v1/hr/employees'));
        $this->assertSame(
            ['GET', 'HEAD', 'PATCH', 'PUT'],
            $methods('/api/v1/hr/employees/{employee}'),
        );
        $this->assertSame(
            ['POST'],
            $methods('/api/v1/hr/employees/{employee}/account-status'),
        );
        $this->assertSame(
            ['GET', 'HEAD'],
            $methods('/api/v1/hr/employees/duplicate-name'),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Authorization */
    /* ------------------------------------------------------------------ */

    public static function protectedRouteProvider(): array
    {
        return [
            'show' => ['GET', '/api/v1/hr/employees/{employee}'],
            'store' => ['POST', '/api/v1/hr/employees'],
            'update' => ['PATCH', '/api/v1/hr/employees/{employee}'],
            'account status' => ['POST', '/api/v1/hr/employees/{employee}/account-status'],
        ];
    }

    /**
     * @dataProvider protectedRouteProvider
     */
    public function test_employee_management_requires_authentication(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->json($method, $this->resolve($uri))->assertUnauthorized();
    }

    /**
     * @dataProvider protectedRouteProvider
     */
    public function test_employee_management_rejects_an_employee_account(string $method, string $uri): void
    {
        // Authorization must be what refuses the caller, so the record has to
        // exist: binding runs first and a missing id would answer 404 and hide
        // a policy regression behind an accidental pass.
        $target = $this->employeeUser($this->department);

        $this->app['auth']->forgetGuards();
        $this->loginAs($this->employeeUser()->user);

        $this->json($method, $this->resolve($uri, $target->id))->assertForbidden();
    }

    /**
     * Substitute a real id into a route template.
     */
    private function resolve(string $uri, ?int $employeeId = null): string
    {
        return str_replace('{employee}', (string) ($employeeId ?? 1), $uri);
    }

    public function test_hr_without_the_employees_permission_cannot_write(): void
    {
        // An employee-role account is denied by role. This asserts the other
        // half: a role that passes the HR portal gate but lacks `employees.*`
        // is still refused, so the HR dashboard permission is not a write pass.
        $this->app['auth']->forgetGuards();

        $head = $this->departmentHeadUser($this->department);

        $this->loginAs($head);
        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-NOPE',
            'first_name' => 'No',
            'last_name' => 'Permission',
            'department_id' => $this->department->id,
        ])->assertForbidden();
    }

    public function test_an_employee_cannot_edit_their_own_record_through_hr(): void
    {
        $self = $this->employeeUser($this->department);

        $this->app['auth']->forgetGuards();
        $this->loginAs($self->user);

        $this->patchJson("/api/v1/hr/employees/{$self->id}", [
            'phone' => '555-0000',
        ])->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /* Create */
    /* ------------------------------------------------------------------ */

    public function test_hr_can_create_an_employee_and_gets_the_full_profile_back(): void
    {
        $position = Position::factory()->create();

        $response = $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-2026-001',
            'first_name' => 'Ana',
            'middle_name' => 'Reyes',
            'last_name' => 'Dela Cruz',
            'department_id' => $this->department->id,
            'position_id' => $position->id,
            'employment_status' => 'active',
            'employment_type' => 'regular',
            'date_hired' => '2026-01-05',
            'phone' => '555-0142',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.personal.employee_number', 'EMP-2026-001')
            ->assertJsonPath('data.personal.full_name', 'Ana Reyes Dela Cruz')
            ->assertJsonPath('data.personal.phone', '555-0142')
            // No email was supplied, so there is deliberately no account.
            ->assertJsonPath('data.employment.has_account', false)
            ->assertJsonPath('data.employment.account_status', null)
            ->assertJsonPath('data.organisation.department', 'College of Nursing')
            ->assertJsonPath('data.organisation.position', $position->name);

        $employee = Employee::query()->where('employee_number', 'EMP-2026-001')->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'hr.employee.create',
            'user_id' => $this->hr->id,
        ]);
    }

    public function test_creating_an_employee_with_an_email_creates_a_usable_employee_login(): void
    {
        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-2026-002',
            'first_name' => 'Belo',
            'last_name' => 'Santos',
            'email' => 'belo.santos@jpc.local',
            'username' => 'belo.santos',
            'password' => 'P@ssw0rd2026',
            'department_id' => $this->department->id,
        ])->assertCreated()
            ->assertJsonPath('data.personal.email', 'belo.santos@jpc.local')
            ->assertJsonPath('data.personal.username', 'belo.santos')
            ->assertJsonPath('data.employment.has_account', true)
            ->assertJsonPath('data.employment.account_status', UserStatus::Active->value)
            // An account with no role could not log in and see anything.
            ->assertJsonPath('data.employment.role', Role::Employee->value);

        $employee = Employee::query()->where('employee_number', 'EMP-2026-002')->firstOrFail();
        $user = $employee->user;

        $this->assertNotNull($user);
        $this->assertTrue(
            Hash::check('P@ssw0rd2026', $user->password),
            'The stored credential must match the submitted password, not a random placeholder.'
        );
        $this->assertTrue($user->hasRole(Role::Employee));
    }

    public function test_creating_an_employee_validates_its_input(): void
    {
        $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'No',
            'last_name' => 'Identifier',
            'department_id' => $this->department->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['employee_number']);

        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'has spaces',
            'first_name' => 'Bad',
            'last_name' => 'Identifier',
            'department_id' => $this->department->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['employee_number']);

        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-2026-003',
            'first_name' => 'No',
            'last_name' => 'Department',
            'department_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['department_id']);

        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-2026-004',
            'first_name' => 'No',
            'last_name' => 'Password',
            'department_id' => $this->department->id,
            'email' => 'no.password@jpc.local',
            'username' => 'no.password',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_employee_numbers_and_account_emails_stay_unique(): void
    {
        $existing = $this->employeeUser($this->department);

        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => $existing->employee_number,
            'first_name' => 'Clash',
            'last_name' => 'Number',
            'department_id' => $this->department->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['employee_number']);

        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-2026-005',
            'first_name' => 'Clash',
            'last_name' => 'Email',
            'department_id' => $this->department->id,
            'email' => $existing->user->email,
            'username' => 'clash.email',
            'password' => 'P@ssw0rd2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /* ------------------------------------------------------------------ */
    /* Update */
    /* ------------------------------------------------------------------ */

    public function test_hr_can_edit_an_employee_and_every_change_is_recorded(): void
    {
        $employee = $this->employeeUser($this->department);
        $newDepartment = Department::factory()->create();

        $response = $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'first_name' => 'Renamed',
            'phone' => '555-9999',
            'department_id' => $newDepartment->id,
            'employment_status' => 'on_leave',
            'reason' => 'Corrected during the HR review.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.personal.first_name', 'Renamed')
            ->assertJsonPath('data.personal.phone', '555-9999')
            ->assertJsonPath('data.employment.employment_status', 'on_leave')
            ->assertJsonPath('data.organisation.department', $newDepartment->name);

        foreach (['first_name', 'phone', 'department_id', 'employment_status'] as $field) {
            $this->assertDatabaseHas('employee_histories', [
                'employee_id' => $employee->id,
                'field_name' => $field,
                'changed_by' => $this->hr->id,
                'reason' => 'Corrected during the HR review.',
            ]);
        }

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'hr.employee.update',
            'user_id' => $this->hr->id,
        ]);
    }

    public function test_an_edit_that_changes_nothing_writes_no_history(): void
    {
        $employee = $this->employeeUser($this->department);

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
        ])->assertOk();

        $this->assertSame(
            0,
            EmployeeHistory::query()->where('employee_id', $employee->id)->count(),
            'A no-op edit must not manufacture audit rows.'
        );
    }

    public function test_an_edit_can_change_the_employee_number(): void
    {
        $employee = $this->employeeUser($this->department);
        $original = $employee->employee_number;

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'employee_number' => 'EMP-2026-999',
        ])->assertOk()
            ->assertJsonPath('data.personal.employee_number', 'EMP-2026-999');

        $this->assertDatabaseHas('employee_histories', [
            'employee_id' => $employee->id,
            'field_name' => 'employee_number',
            'old_value' => $original,
            'new_value' => 'EMP-2026-999',
        ]);
    }

    public function test_editing_an_email_keeps_the_account_and_can_reset_the_password(): void
    {
        $employee = $this->employeeUser($this->department);
        $originalUserId = $employee->user_id;

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'email' => 'renamed.employee@jpc.local',
            'password' => 'N3wP@ssw0rd!',
        ])->assertOk()
            ->assertJsonPath('data.personal.email', 'renamed.employee@jpc.local');

        $employee->refresh();

        $this->assertSame($originalUserId, $employee->user_id, 'Editing must reuse the account, not create a second one.');
        $this->assertTrue(
            Hash::check('N3wP@ssw0rd!', $employee->user->password),
        );
    }

    public function test_a_rename_propagates_to_the_linked_account_name(): void
    {
        $employee = $this->employeeUser($this->department);

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'first_name' => 'Consistent',
        ])->assertOk();

        $this->assertStringStartsWith(
            'Consistent ',
            $employee->refresh()->user->name,
            'The account display name is derived from the employee name and must not drift.'
        );
    }

    public function test_editing_an_employee_validates_its_input(): void
    {
        $employee = $this->employeeUser($this->department);

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'employment_status' => 'retired-imaginary',
        ])->assertStatus(422)->assertJsonValidationErrors(['employment_status']);

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'supervisor_id' => $employee->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['supervisor_id']);

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'date_of_birth' => '2999-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_editing_an_unknown_employee_is_a_404(): void
    {
        $this->patchJson('/api/v1/hr/employees/999999', ['phone' => '555-0000'])
            ->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Account status */
    /* ------------------------------------------------------------------ */

    public function test_hr_can_deactivate_and_reactivate_an_account(): void
    {
        $employee = $this->employeeUser($this->department);

        $this->postJson("/api/v1/hr/employees/{$employee->id}/account-status", [
            'account_status' => UserStatus::Inactive->value,
        ])->assertOk()
            ->assertJsonPath('data.employment.account_status', UserStatus::Inactive->value);

        // Deactivating must never delete the record or its audit trail.
        $this->assertDatabaseHas('users', ['id' => $employee->user_id]);
        $this->assertDatabaseHas('employee_histories', [
            'employee_id' => $employee->id,
            'field_name' => 'account_status',
            'new_value' => UserStatus::Inactive->value,
        ]);

        $this->postJson("/api/v1/hr/employees/{$employee->id}/account-status", [
            'account_status' => UserStatus::Active->value,
        ])->assertOk()
            ->assertJsonPath('data.employment.account_status', UserStatus::Active->value);
    }

    public function test_account_status_requires_a_real_value(): void
    {
        $employee = $this->employeeUser($this->department);

        $this->postJson("/api/v1/hr/employees/{$employee->id}/account-status", [
            'account_status' => 'vibing',
        ])->assertStatus(422)->assertJsonValidationErrors(['account_status']);
    }

    public function test_account_status_on_an_employee_without_an_account_explains_itself(): void
    {
        $employee = Employee::factory()->create(['department_id' => $this->department->id]);

        $this->postJson("/api/v1/hr/employees/{$employee->id}/account-status", [
            'account_status' => UserStatus::Active->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['account_status']);
    }

    /* ------------------------------------------------------------------ */
    /* Profile honesty */
    /* ------------------------------------------------------------------ */

    public function test_profile_counts_are_real_and_rates_are_null_when_unmeasured(): void
    {
        $employee = $this->employeeUser($this->department);

        $task = Task::factory()->create();
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'completed',
        ]);
        // A separate task: (task_id, employee_id) is unique, so the same task
        // cannot be assigned twice to the same person.
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => Task::factory()->create()->id,
            'status' => 'in_progress',
        ]);

        $response = $this->getJson("/api/v1/hr/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonPath('data.tasks.total', 2)
            ->assertJsonPath('data.tasks.completed', 1)
            ->assertJsonPath('data.tasks.pending', 1)
            // JSON has no int/float distinction, so `50` and `50.0` are the same
            // wire value; assert the number rather than its PHP type.
            ->assertJsonPath('data.performance.completion_rate', fn ($value) => $value === 50 || $value === 50.0)
            // Nothing has been submitted, so there is nothing to divide by.
            ->assertJsonPath('data.performance.on_time_submission_rate', null)
            ->assertJsonPath('data.performance.approval_rate', null);
    }

    public function test_profile_late_task_count_matches_the_directory_row(): void
    {
        $employee = $this->employeeUser($this->department);

        // An assignment with no deadline of its own inherits a past one from the
        // parent task, which is the rule the directory list also applies.
        $task = Task::factory()->create(['due_date' => now()->subDay()]);
        TaskAssignment::factory()->create([
            'employee_id' => $employee->id,
            'task_id' => $task->id,
            'status' => 'in_progress',
            'due_date' => null,
            'completed_at' => null,
        ]);

        $this->getJson("/api/v1/hr/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.tasks.late', 1);

        $list = $this->getJson('/api/v1/hr/employees?search='.$employee->employee_number);
        $list->assertOk();

        $row = collect($list->json('data.items'))
            ->firstWhere('employee_number', $employee->employee_number);

        $this->assertNotNull($row);
        $this->assertSame(
            $row['late_tasks'],
            $this->getJson("/api/v1/hr/employees/{$employee->id}")->json('data.tasks.late'),
            'The profile and the directory row must agree about lateness.'
        );
    }

    public function test_profile_reports_the_linked_account_and_last_login(): void
    {
        $employee = $this->employeeUser($this->department);
        $employee->user->forceFill([
            'last_login_at' => now(),
            'status' => UserStatus::Active->value,
        ])->save();

        $this->getJson("/api/v1/hr/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.employment.has_account', true)
            ->assertJsonPath('data.employment.email', null)
            ->assertJsonPath('data.employment.role', Role::Employee->value)
            ->assertJsonStructure(['data' => ['employment' => ['last_login_at']]]);
    }

    public function test_profile_omits_soft_deleted_employees(): void
    {
        $employee = $this->employeeUser($this->department);
        $employee->delete();

        $this->getJson("/api/v1/hr/employees/{$employee->id}")->assertNotFound();
    }

    public function test_directory_exposes_account_and_email_columns(): void
    {
        $employee = $this->employeeUser($this->department);
        $employee->user->forceFill(['status' => UserStatus::Inactive->value])->save();

        $this->getJson('/api/v1/hr/employees?search='.$employee->employee_number)
            ->assertOk()
            ->assertJsonPath('data.items.0.email', $employee->user->email)
            ->assertJsonPath('data.items.0.account_status', UserStatus::Inactive->value)
            ->assertJsonPath('data.items.0.has_account', true);

        $this->getJson('/api/v1/hr/employees?account_status=inactive')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/hr/employees?account_status=nonsense')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_status']);
    }

    /* ------------------------------------------------------------------ */
    /* Duplicate names and usernames */
    /* ------------------------------------------------------------------ */

    public function test_duplicate_name_check_is_case_and_whitespace_insensitive(): void
    {
        $existing = Employee::factory()->create([
            'first_name' => 'Ana Maria',
            'middle_name' => 'Reyes',
            'last_name' => 'Dela Cruz',
            'department_id' => $this->department->id,
        ]);

        $this->getJson('/api/v1/hr/employees/duplicate-name?'.http_build_query([
            'first_name' => '  ana   MARIA ',
            'middle_name' => ' reyes ',
            'last_name' => 'DELA   cruz',
        ]))->assertOk()
            ->assertJsonPath('data.normalized_name', 'ana maria reyes dela cruz')
            ->assertJsonPath('data.matches.0.id', $existing->id)
            ->assertJsonPath('data.matches.0.employee_number', $existing->employee_number)
            ->assertJsonPath('data.matches.0.department', 'College of Nursing');
    }

    public function test_duplicate_name_check_returns_no_matches_for_a_different_name(): void
    {
        Employee::factory()->create([
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Dela Cruz',
            'department_id' => $this->department->id,
        ]);

        $this->getJson('/api/v1/hr/employees/duplicate-name?'.http_build_query([
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Santos',
        ]))->assertOk()->assertJsonCount(0, 'data.matches');
    }

    public function test_duplicate_name_check_can_exclude_the_employee_being_edited(): void
    {
        $employee = Employee::factory()->create([
            'first_name' => 'Ana',
            'middle_name' => null,
            'last_name' => 'Dela Cruz',
            'department_id' => $this->department->id,
        ]);

        $this->getJson('/api/v1/hr/employees/duplicate-name?'.http_build_query([
            'first_name' => 'Ana',
            'last_name' => 'Dela Cruz',
            'exclude_id' => $employee->id,
        ]))->assertOk()->assertJsonCount(0, 'data.matches');
    }

    public function test_duplicate_name_check_validates_required_names(): void
    {
        $this->getJson('/api/v1/hr/employees/duplicate-name')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_employee_account_usernames_stay_unique(): void
    {
        $existing = $this->employeeUser($this->department);
        $existing->user->forceFill(['username' => 'taken.username'])->save();

        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-2026-006',
            'first_name' => 'Clash',
            'last_name' => 'Username',
            'department_id' => $this->department->id,
            'email' => 'clash.username@jpc.local',
            'username' => 'taken.username',
            'password' => 'P@ssw0rd2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_employee_account_requires_a_username(): void
    {
        $this->postJson('/api/v1/hr/employees', [
            'employee_number' => 'EMP-2026-007',
            'first_name' => 'No',
            'last_name' => 'Username',
            'department_id' => $this->department->id,
            'email' => 'no.username@jpc.local',
            'password' => 'P@ssw0rd2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_editing_an_employee_cannot_take_another_accounts_username(): void
    {
        $employee = $this->employeeUser($this->department);
        $other = $this->employeeUser($this->department);
        $other->user->forceFill(['username' => 'owned.elsewhere'])->save();

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'username' => 'owned.elsewhere',
        ])->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_editing_an_employee_keeps_its_own_username(): void
    {
        $employee = $this->employeeUser($this->department);
        $employee->user->forceFill(['username' => 'same.username'])->save();

        $this->patchJson("/api/v1/hr/employees/{$employee->id}", [
            'username' => 'same.username',
            'phone' => '555-7777',
        ])->assertOk()
            ->assertJsonPath('data.personal.username', 'same.username')
            ->assertJsonPath('data.personal.phone', '555-7777');
    }
}
