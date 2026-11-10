<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\EmployeeTargetAdjustmentRequest;
use App\Models\EmployeeTargetBreakdown;
use App\Models\EmployeeTargetMilestone;
use App\Models\EmployeeTargetNote;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * My Targets — administrative review of target adjustment requests.
 *
 * Admin may review the request queue, approve (which applies the change while
 * snapshotting the original values) or reject (which leaves the target intact),
 * and read a target's breakdowns, milestones and achievement notes.
 */
class AdminTargetAdjustmentApiTest extends TestCase
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

    private function adminUser(): User
    {
        return User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    private function pendingRequest(EmployeeTarget $target, Employee $employee): EmployeeTargetAdjustmentRequest
    {
        return EmployeeTargetAdjustmentRequest::factory()->create([
            'employee_target_id' => $target->id,
            'employee_id' => $employee->id,
            'reason' => 'Scope changed after the kick-off meeting.',
            'request_type' => 'target_value',
            'proposed_target_value' => 250,
            'proposed_end_date' => now()->addMonths(3)->toDateString(),
            'status' => EmployeeTargetAdjustmentRequest::STATUS_PENDING,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');

        $this->getJson('/api/v1/admin/target-adjustments')->assertUnauthorized();
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        $this->login($user);

        $this->getJson('/api/v1/admin/target-adjustments')->assertForbidden();
    }

    public function test_queue_lists_requests_with_a_summary(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create(['user_id' => null]);
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);
        $request = $this->pendingRequest($target, $employee);

        $this->login($admin);

        $this->getJson('/api/v1/admin/target-adjustments')
            ->assertOk()
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.items.total', 1)
            ->assertJsonPath('data.items.data.0.id', $request->id);
    }

    public function test_approving_applies_the_change_and_preserves_the_original(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create(['user_id' => null]);
        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 100,
            'target_unit' => 'reports',
        ]);
        $request = $this->pendingRequest($target, $employee);

        $this->login($admin);

        $this->postJson("/api/v1/admin/target-adjustments/{$request->id}/approve", [
            'decision_note' => 'Approved given the expanded scope.',
        ])->assertOk()
            ->assertJsonPath('data.status', EmployeeTargetAdjustmentRequest::STATUS_APPROVED)
            ->assertJsonPath('data.reviewed_by', $admin->name);

        $fresh = $target->fresh();
        $this->assertEquals(250, (float) $fresh->target_value);

        $reviewed = $request->fresh();
        $this->assertNotNull($reviewed->applied_at);
        $this->assertEquals(100, (float) $reviewed->original_snapshot['target_value']);
        $this->assertSame('Approved given the expanded scope.', $reviewed->decision_note);

        $this->assertDatabaseHas('activity_logs', ['activity_type' => 'target.adjustment.approved']);
    }

    public function test_rejecting_leaves_the_target_untouched(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create(['user_id' => null]);
        $target = EmployeeTarget::factory()->create([
            'employee_id' => $employee->id,
            'target_value' => 100,
        ]);
        $request = $this->pendingRequest($target, $employee);

        $this->login($admin);

        $this->postJson("/api/v1/admin/target-adjustments/{$request->id}/reject", [
            'decision_note' => 'Not sufficiently justified.',
        ])->assertOk()
            ->assertJsonPath('data.status', EmployeeTargetAdjustmentRequest::STATUS_REJECTED);

        $this->assertEquals(100, (float) $target->fresh()->target_value);
        $this->assertNull($request->fresh()->applied_at);

        $this->assertDatabaseHas('activity_logs', ['activity_type' => 'target.adjustment.rejected']);
    }

    public function test_a_request_can_only_be_reviewed_once(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create(['user_id' => null]);
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);
        $request = EmployeeTargetAdjustmentRequest::factory()->approved()->create([
            'employee_target_id' => $target->id,
            'employee_id' => $employee->id,
        ]);

        $this->login($admin);

        $this->postJson("/api/v1/admin/target-adjustments/{$request->id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_can_review_a_targets_breakdowns_milestones_and_notes(): void
    {
        $admin = $this->adminUser();
        $employee = Employee::factory()->create(['user_id' => null]);
        $target = EmployeeTarget::factory()->create(['employee_id' => $employee->id]);

        EmployeeTargetBreakdown::factory()->create(['employee_target_id' => $target->id, 'progress' => 30]);
        EmployeeTargetMilestone::factory()->create(['employee_target_id' => $target->id]);
        EmployeeTargetNote::factory()->create([
            'employee_target_id' => $target->id,
            'employee_id' => $employee->id,
        ]);

        $this->login($admin);

        $this->getJson("/api/v1/admin/targets/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.target.id', $target->id)
            ->assertJsonCount(1, 'data.breakdowns')
            ->assertJsonCount(1, 'data.milestones')
            ->assertJsonCount(1, 'data.notes');

        $this->getJson('/api/v1/admin/targets/999999')->assertNotFound();
    }
}
