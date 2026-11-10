<?php

namespace Tests\Feature\Api\V1\Employee;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\EmployeeTargetNote;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * My Targets — employee self-service workspace.
 *
 * Covers the five target enhancements end to end through the API:
 *  - breakdown (sub-target progress rolls up into the parent target);
 *  - milestones (auto-achieved from real progress, completion date recorded);
 *  - adjustment requests (reason required, recorded as pending);
 *  - achievement notes (author-only management);
 *  - AI target suggestions (own targets only, gated to the Employee role).
 *
 * Authorization is asserted at the same time: the employee is always resolved
 * from the authenticated user, and another employee's target is a 404.
 */
class EmployeeTargetWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function employeeUser(): array
    {
        $employee = Employee::factory()->create(['user_id' => null]);
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        $employee->update(['user_id' => $user->id]);

        return [$user, $employee->fresh()];
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');

        $this->getJson('/api/v1/employee/targets')->assertUnauthorized();
    }

    public function test_account_without_employee_profile_is_forbidden(): void
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
        $this->login($user);

        $this->getJson('/api/v1/employee/targets')->assertForbidden();
    }

    public function test_index_returns_only_the_authenticated_employees_targets(): void
    {
        [$user, $employee] = $this->employeeUser();

        EmployeeTarget::factory()->create(['employee_id' => $employee->id, 'title' => 'MY TARGET']);

        $otherEmployee = Employee::factory()->create(['user_id' => null]);
        EmployeeTarget::factory()->create(['employee_id' => $otherEmployee->id, 'title' => 'OTHER TARGET']);

        $this->login($user);

        $response = $this->getJson('/api/v1/employee/targets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items');

        $titles = array_column($response->json('data.items'), 'title');
        $this->assertSame(['MY TARGET'], $titles);
    }

    public function test_breakdown_progress_rolls_up_into_the_parent_target(): void
    {
        [$user, $employee] = $this->employeeUser();
        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 100,
        ]);
        $this->login($user);

        $this->postJson("/api/v1/employee/targets/{$target->id}/breakdowns", [
            'title' => 'Phase 1',
            'progress' => 40,
        ])->assertCreated()->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/employee/targets/{$target->id}/breakdowns", [
            'title' => 'Phase 2',
            'progress' => 60,
        ])->assertCreated()->assertJsonPath('data.status', 'in_progress');

        $detail = $this->getJson("/api/v1/employee/targets/{$target->id}")->assertOk();

        $this->assertEquals(50, $detail->json('data.target.progress'));
        $this->assertSame('breakdowns', $detail->json('data.target.progress_source'));
        $this->assertCount(2, $detail->json('data.breakdowns'));

        $this->assertEquals(
            50,
            $this->getJson('/api/v1/employee/targets')->json('data.items.0.progress'),
        );
    }

    public function test_completing_every_breakdown_marks_the_parent_completed(): void
    {
        [$user, $employee] = $this->employeeUser();
        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'status' => 'active',
        ]);
        $this->login($user);

        $this->postJson("/api/v1/employee/targets/{$target->id}/breakdowns", [
            'title' => 'The only sub-target',
            'progress' => 100,
        ])->assertCreated()->assertJsonPath('data.status', 'completed');

        $this->assertSame('completed', $target->fresh()->status);
        $this->assertEquals(
            100,
            $this->getJson("/api/v1/employee/targets/{$target->id}")->json('data.target.progress'),
        );
    }

    public function test_milestone_is_auto_achieved_with_a_completion_date(): void
    {
        [$user, $employee] = $this->employeeUser();
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);
        $this->login($user);

        $breakdown = $this->postJson("/api/v1/employee/targets/{$target->id}/breakdowns", [
            'title' => 'Deliverable',
            'progress' => 0,
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/employee/targets/{$target->id}/milestones", [
            'title' => 'Halfway checkpoint',
            'threshold_percent' => 50,
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->putJson("/api/v1/employee/targets/{$target->id}/breakdowns/{$breakdown['id']}", [
            'progress' => 100,
        ])->assertOk();

        $milestones = $this->getJson("/api/v1/employee/targets/{$target->id}/milestones")
            ->assertOk()
            ->json('data');

        $this->assertSame('achieved', $milestones[0]['status']);
        $this->assertNotNull($milestones[0]['achieved_at']);
    }

    public function test_adjustment_request_requires_a_reason(): void
    {
        [$user, $employee] = $this->employeeUser();
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);
        $this->login($user);

        $this->postJson("/api/v1/employee/targets/{$target->id}/adjustments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_adjustment_request_is_recorded_as_pending(): void
    {
        [$user, $employee] = $this->employeeUser();
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);
        $this->login($user);

        $this->postJson("/api/v1/employee/targets/{$target->id}/adjustments", [
            'reason' => 'The original deadline is no longer realistic.',
            'request_type' => 'deadline',
            'proposed_end_date' => now()->addMonths(2)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('employee_target_adjustment_requests', [
            'employee_target_id' => $target->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
            'request_type' => 'deadline',
        ]);

        $this->assertDatabaseHas('activity_logs', ['activity_type' => 'target.adjustment.requested']);
    }

    public function test_employee_cannot_access_another_employees_target(): void
    {
        [$user] = $this->employeeUser();
        $otherEmployee = Employee::factory()->create(['user_id' => null]);
        $otherTarget = EmployeeTarget::factory()->create(['employee_id' => $otherEmployee->id]);
        $this->login($user);

        $this->getJson("/api/v1/employee/targets/{$otherTarget->id}")->assertNotFound();
        $this->getJson("/api/v1/employee/targets/{$otherTarget->id}/breakdowns")->assertNotFound();
        $this->getJson("/api/v1/employee/targets/{$otherTarget->id}/milestones")->assertNotFound();
        $this->postJson("/api/v1/employee/targets/{$otherTarget->id}/notes", ['body' => 'nope'])
            ->assertNotFound();
    }

    public function test_notes_can_be_managed_by_the_owner(): void
    {
        [$user, $employee] = $this->employeeUser();
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);
        $this->login($user);

        $note = $this->postJson("/api/v1/employee/targets/{$target->id}/notes", [
            'body' => 'Delivered phase one ahead of schedule.',
        ])->assertCreated()->json('data');

        $this->putJson("/api/v1/employee/targets/{$target->id}/notes/{$note['id']}", [
            'body' => 'Delivered phase one and phase two ahead of schedule.',
        ])->assertOk()
            ->assertJsonPath('data.body', 'Delivered phase one and phase two ahead of schedule.');

        $this->deleteJson("/api/v1/employee/targets/{$target->id}/notes/{$note['id']}")->assertOk();

        $this->assertDatabaseMissing('employee_target_notes', ['id' => $note['id']]);
    }

    public function test_note_authored_by_another_employee_cannot_be_modified(): void
    {
        [$user, $employee] = $this->employeeUser();
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);

        $otherEmployee = Employee::factory()->create(['user_id' => null]);
        $note = EmployeeTargetNote::factory()->create([
            'employee_target_id' => $target->id,
            'employee_id' => $otherEmployee->id,
        ]);

        $this->login($user);

        $this->putJson("/api/v1/employee/targets/{$target->id}/notes/{$note->id}", ['body' => 'hijack'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/employee/targets/{$target->id}/notes/{$note->id}")
            ->assertForbidden();
    }

    public function test_suggestions_analyse_only_the_callers_own_targets(): void
    {
        [$user, $employee] = $this->employeeUser();
        $mine = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);

        $otherEmployee = Employee::factory()->create(['user_id' => null]);
        EmployeeTarget::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->login($user);

        $response = $this->getJson('/api/v1/employee/targets/suggestions')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.targets_analyzed', 1);

        $ids = array_values(array_unique(array_column($response->json('data.suggestions'), 'target_id')));
        $this->assertSame([$mine->id], $ids);
    }

    public function test_suggestions_are_forbidden_outside_the_employee_role(): void
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $this->login($user);

        $this->getJson('/api/v1/employee/targets/suggestions')->assertForbidden();
    }
}
