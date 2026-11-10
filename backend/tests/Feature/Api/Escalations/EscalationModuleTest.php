<?php

namespace Tests\Feature\Api\Escalations;

use App\Enums\Role;
use App\Models\Department;
use App\Models\DepartmentEscalation;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\User;
use App\Services\Escalations\EscalationNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for the Department Head escalations module.
 *
 * Exercises role-based access control, department scoping, validation,
 * the review workflow (assign / status transitions / notes), duplicate
 * notification prevention and the isolation between escalations and the
 * ordinary task reminder / late-submission machinery.
 */
class EscalationModuleTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $admin;

    private User $head;

    private Employee $employee;

    private Task $task;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);

        $this->department = Department::factory()->create();
        $this->admin = $this->userWithRole(Role::Admin);

        $this->head = $this->userWithRole(Role::DepartmentHead);
        Employee::factory()->create(['user_id' => $this->head->id, 'department_id' => $this->department->id]);

        $this->employee = Employee::factory()->create(['department_id' => $this->department->id]);
        $this->task = Task::factory()->create(['department_id' => $this->department->id]);
        $this->report = Report::factory()->create([
            'employee_id' => $this->employee->id,
            'task_id' => $this->task->id,
        ]);
    }

    // ─── Access control ─────────────────────────────────────────────────────

    public function test_department_head_can_list_escalations_with_summary(): void
    {
        $this->makeEscalation();

        $this->login($this->head);

        $this->getJson('/api/v1/department-head/escalations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_employee_and_hr_are_denied_access(): void
    {
        $this->login($this->userWithRole(Role::Employee));
        $this->getJson('/api/v1/department-head/escalations')->assertForbidden();

        $this->login($this->userWithRole(Role::Hr));
        $this->getJson('/api/v1/admin/escalations')->assertForbidden();
    }

    public function test_department_head_cannot_use_administration_workflow(): void
    {
        $escalation = $this->makeEscalation();

        $this->login($this->head);

        $this->postJson("/api/v1/admin/escalations/{$escalation->id}/status", [
            'status' => 'under_review',
        ])->assertForbidden();
    }

    public function test_department_head_cannot_view_another_departments_escalation(): void
    {
        $escalation = $this->makeEscalation();

        $otherDepartment = Department::factory()->create();
        $otherHead = $this->userWithRole(Role::DepartmentHead);
        Employee::factory()->create(['user_id' => $otherHead->id, 'department_id' => $otherDepartment->id]);

        $this->login($otherHead);

        $this->getJson("/api/v1/department-head/escalations/{$escalation->id}")
            ->assertForbidden();
    }

    // ─── Creation ───────────────────────────────────────────────────────────

    public function test_department_head_can_create_an_escalation(): void
    {
        $this->login($this->head);

        $this->postJson('/api/v1/department-head/escalations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.employee_id', $this->employee->id)
            ->assertJsonPath('data.type_label', 'Repeated Late Submission');

        $this->assertDatabaseHas('department_escalations', [
            'department_id' => $this->department->id,
            'employee_id' => $this->employee->id,
            'created_by' => $this->head->id,
            'status' => 'pending',
        ]);

        // An append-only history entry and an audit record are written.
        $escalation = DepartmentEscalation::query()->firstOrFail();
        $this->assertDatabaseHas('escalation_histories', [
            'escalation_id' => $escalation->id,
            'user_id' => $this->head->id,
            'action' => 'created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'department_head.escalation.created',
            'entity_id' => $escalation->id,
        ]);

        // Administration is alerted through the existing JPC notification inbox.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'type' => 'escalation_created',
        ]);
    }

    public function test_creation_requires_the_mandatory_fields(): void
    {
        $this->login($this->head);

        $this->postJson('/api/v1/department-head/escalations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'type', 'reason', 'employee_id', 'priority']);
    }

    public function test_department_head_cannot_escalate_an_employee_from_another_department(): void
    {
        $foreignEmployee = Employee::factory()->create([
            'department_id' => Department::factory()->create()->id,
        ]);

        $this->login($this->head);

        $this->postJson('/api/v1/department-head/escalations', $this->payload([
            'employee_id' => $foreignEmployee->id,
        ]))->assertForbidden();
    }

    public function test_an_unknown_related_record_is_rejected(): void
    {
        $this->login($this->head);

        $this->postJson('/api/v1/department-head/escalations', $this->payload([
            'related_type' => 'report',
            'related_id' => 999999,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['related_id']);
    }

    public function test_a_report_from_another_employee_is_rejected(): void
    {
        $otherReport = Report::factory()->create([
            'employee_id' => Employee::factory()->create(['department_id' => $this->department->id])->id,
            'task_id' => $this->task->id,
        ]);

        $this->login($this->head);

        $this->postJson('/api/v1/department-head/escalations', $this->payload([
            'related_type' => 'report',
            'related_id' => $otherReport->id,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['related_id']);
    }

    // ─── Administration workflow ────────────────────────────────────────────

    public function test_admin_can_assign_review_resolve_and_notify(): void
    {
        $escalation = $this->makeEscalation();

        $this->login($this->admin);

        // Assign → a still-pending escalation moves to under review.
        $this->postJson("/api/v1/admin/escalations/{$escalation->id}/assign", [
            'assigned_to' => $this->admin->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.assigned_to', $this->admin->id);

        // Resolve, recording the resolution note.
        $this->postJson("/api/v1/admin/escalations/{$escalation->id}/status", [
            'status' => 'resolved',
            'resolution_notes' => 'Guidance memo issued to the employee.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution_notes', 'Guidance memo issued to the employee.');

        $this->assertDatabaseHas('escalation_histories', [
            'escalation_id' => $escalation->id,
            'action' => 'assigned',
        ]);
        $this->assertDatabaseHas('escalation_histories', [
            'escalation_id' => $escalation->id,
            'action' => 'resolved',
            'new_status' => 'resolved',
        ]);

        // The originating Department Head is notified of the resolution.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->head->id,
            'type' => 'escalation_resolved',
        ]);
    }

    public function test_resolving_requires_a_resolution_note(): void
    {
        $escalation = $this->makeEscalation();

        $this->login($this->admin);

        $this->postJson("/api/v1/admin/escalations/{$escalation->id}/status", [
            'status' => 'resolved',
        ])->assertStatus(422)->assertJsonValidationErrors(['resolution_notes']);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        $escalation = $this->makeEscalation(['status' => 'under_review']);

        $this->login($this->admin);

        // Under review → under review is not a permitted transition.
        $this->postJson("/api/v1/admin/escalations/{$escalation->id}/status", [
            'status' => 'under_review',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);

        // A finalized escalation can no longer be changed.
        $escalation->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->postJson("/api/v1/admin/escalations/{$escalation->id}/status", [
            'status' => 'action_required',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_internal_notes_are_hidden_from_the_department_head(): void
    {
        $escalation = $this->makeEscalation();

        $this->login($this->admin);

        $this->postJson("/api/v1/admin/escalations/{$escalation->id}/notes", [
            'note' => 'Confidential HR follow-up required.',
            'is_internal' => true,
        ])->assertOk();

        // Administration sees the internal note...
        $this->getJson("/api/v1/admin/escalations/{$escalation->id}")
            ->assertOk()
            ->assertJsonFragment(['note' => 'Confidential HR follow-up required.']);

        // ...the Department Head does not.
        $this->login($this->head);

        $this->getJson("/api/v1/department-head/escalations/{$escalation->id}")
            ->assertOk()
            ->assertJsonMissing(['note' => 'Confidential HR follow-up required.']);
    }

    // ─── Notification integrity ─────────────────────────────────────────────

    public function test_creation_alerts_are_not_duplicated(): void
    {
        $this->login($this->head);

        $this->postJson('/api/v1/department-head/escalations', $this->payload())
            ->assertCreated();

        $escalation = DepartmentEscalation::query()->firstOrFail();

        $this->assertSame(1, Notification::query()
            ->where('user_id', $this->admin->id)
            ->where('type', 'escalation_created')
            ->count());

        // Re-dispatching the same alert is idempotent.
        app(EscalationNotificationService::class)->notifyCreated($escalation->fresh());

        $this->assertSame(1, Notification::query()
            ->where('user_id', $this->admin->id)
            ->where('type', 'escalation_created')
            ->count());
    }

    public function test_escalations_do_not_touch_the_task_reminder_system(): void
    {
        $this->makeEscalation();

        // Raising an escalation must never invent deadline/overdue alerts.
        $this->assertSame(0, Notification::query()
            ->whereIn('type', ['task_deadline', 'task_overdue', 'task_due_today'])
            ->count());
    }

    public function test_open_scope_excludes_terminal_escalations(): void
    {
        $this->makeEscalation(['status' => 'pending']);
        $this->makeEscalation(['status' => 'under_review']);
        $this->makeEscalation(['status' => 'action_required']);
        $this->makeEscalation(['status' => 'resolved']);
        $this->makeEscalation(['status' => 'rejected']);

        $this->assertSame(3, DepartmentEscalation::query()->open()->count());
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    private function userWithRole(Role $role): User
    {
        $roleId = RoleModel::query()->where('name', $role->value)->firstOrFail()->id;

        return User::factory()->create(['role_id' => $roleId]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEscalation(array $overrides = []): DepartmentEscalation
    {
        return DepartmentEscalation::create(array_merge([
            'department_id' => $this->department->id,
            'employee_id' => $this->employee->id,
            'created_by' => $this->head->id,
            'title' => 'Repeated late submission on syllabus delivery',
            'type' => 'repeated_late_submission',
            'reason' => 'The employee submitted the required report late four times this quarter.',
            'priority' => 'high',
            'status' => 'pending',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Repeated late submission on syllabus delivery',
            'type' => 'repeated_late_submission',
            'reason' => 'The employee submitted the required report late four times this quarter.',
            'employee_id' => $this->employee->id,
            'priority' => 'high',
            'requested_action' => 'Please review and issue a formal guidance memo.',
        ], $overrides);
    }
}
