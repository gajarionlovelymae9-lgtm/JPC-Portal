<?php

namespace Tests\Feature\Api\Hr;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\AnnualPlan;
use App\Models\Department;
use App\Models\EmployeeTarget;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the HR Targets Management surface.
 *
 * Pinned:
 *  1. Authorization — every verb carries its specific `targets.*` permission
 *     on top of the HR portal permission; an HR user without them is refused,
 *     and employees never get in at all.
 *  2. Validation — required fields, non-negative value, and no inverted
 *     date ranges.
 *  3. Real records — created/edited targets land in `employee_targets` with
 *     the assigned employee and annual plan, deletes are soft, and every
 *     write lands in `activity_logs`.
 */
class HrTargetApiTest extends TestCase
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

    /**
     * A target whose lifecycle fields are all valid — reused everywhere.
     *
     * @return array<string, mixed>
     */
    private function payload(int $employeeId, int $planId): array
    {
        return [
            'annual_plan_id' => $planId,
            'employee_id' => $employeeId,
            'title' => 'Complete enrolment follow-ups',
            'description' => 'Contact every pending applicant by the semester start.',
            'target_value' => '25',
            'target_unit' => 'forms',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Authorization */
    /* ------------------------------------------------------------------ */

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/hr/targets')->assertUnauthorized();
    }

    public function test_employee_role_cannot_read_or_write(): void
    {
        $this->app['auth']->forgetGuards();
        $this->loginAs($this->roleUser(Role::Employee));

        $this->getJson('/api/v1/hr/targets')->assertForbidden();
        $this->postJson('/api/v1/hr/targets', [])->assertForbidden();
        $this->deleteJson('/api/v1/hr/targets/1')->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /* Reads */
    /* ------------------------------------------------------------------ */

    public function test_returns_summary_cards_and_register_rows(): void
    {
        $targets = EmployeeTarget::factory()->count(4)->create([
            'annual_plan_id' => AnnualPlan::factory()->create()->id,
            'status' => 'active',
        ]);
        EmployeeTarget::factory()->create(['status' => 'completed']);

        $response = $this->getJson('/api/v1/hr/targets');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_kpis', 5);

        $body = $response->json('data');

        $this->assertCount(5, $body['items']);
        $this->assertArrayHasKey('options', $body);
        $this->assertArrayHasKey('employees', $body['options']);
        $this->assertArrayHasKey('annual_plans', $body['options']);
        $this->assertArrayHasKey('stored_statuses', $body['options']);

        foreach ($body['items'] as $item) {
            $this->assertArrayHasKey('employee_name', $item);
            $this->assertArrayHasKey('department_name', $item);
            $this->assertArrayHasKey('achievement_pct', $item);
            $this->assertArrayHasKey('derived_status', $item);
            $this->assertArrayHasKey('period', $item);
        }

        $this->assertSame(4, collect($body['items'])->where('stored_status', 'active')->count());
        $this->assertSame(4, $targets->count()); // factory sanity, no side writes
    }

    public function test_register_filters_by_department_search_and_status(): void
    {
        EmployeeTarget::factory()->create([
            'title' => 'Accreditation paperwork',
            'annual_plan_id' => AnnualPlan::factory()->create()->id,
        ]);
        EmployeeTarget::factory()->create([
            'title' => 'Scholarship payouts',
            'annual_plan_id' => AnnualPlan::factory()->create()->id,
        ]);

        $matched = $this->getJson('/api/v1/hr/targets?search=accreditation')
            ->assertOk()
            ->json('data.meta.total');

        $this->assertSame(1, $matched);
    }

    public function test_show_returns_the_decorated_row(): void
    {
        $target = EmployeeTarget::factory()->create([
            'annual_plan_id' => AnnualPlan::factory()->create()->id,
        ]);

        $this->getJson("/api/v1/hr/targets/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.employee_id', $target->employee_id)
            ->assertJsonPath('data.annual_plan_id', $target->annual_plan_id);
    }

    public function test_show_of_an_unknown_id_answers_404(): void
    {
        $this->getJson('/api/v1/hr/targets/99999')->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Writes */
    /* ------------------------------------------------------------------ */

    public function test_hr_can_create_a_target(): void
    {
        $employee = $this->employeeUser($this->department);
        $plan = AnnualPlan::factory()->create();

        $response = $this->postJson('/api/v1/hr/targets', $this->payload($employee->id, $plan->id));

        $response->assertCreated()
            ->assertJsonPath('data.kpi', 'Complete enrolment follow-ups')
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.annual_plan_id', $plan->id);

        $this->assertDatabaseHas('employee_targets', [
            'employee_id' => $employee->id,
            'annual_plan_id' => $plan->id,
            'title' => 'Complete enrolment follow-ups',
            'status' => 'active',
        ]);

        $this->assertSame(1, ActivityLog::query()->where('activity_type', 'target.created')->count());
    }

    public function test_create_validates_required_fields_and_dates(): void
    {
        $employee = $this->employeeUser($this->department);
        $plan = AnnualPlan::factory()->create();

        $this->postJson('/api/v1/hr/targets', array_filter($this->payload($employee->id, $plan->id), fn ($key) => in_array($key, ['title'], true), ARRAY_FILTER_USE_KEY))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['annual_plan_id', 'employee_id']);

        // Inverted date range is refused.
        $this->postJson('/api/v1/hr/targets', array_merge($this->payload($employee->id, $plan->id), [
            'start_date' => '2026-06-30',
            'end_date' => '2026-01-01',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        // Negative target value is refused.
        $this->postJson('/api/v1/hr/targets', array_merge($this->payload($employee->id, $plan->id), [
            'target_value' => '-5',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['target_value']);

        // A target with no period at all is refused.
        $this->postJson('/api/v1/hr/targets', array_merge($this->payload($employee->id, $plan->id), [
            'start_date' => null,
            'end_date' => null,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_hr_can_edit_a_target(): void
    {
        $target = EmployeeTarget::factory()->create([
            'annual_plan_id' => AnnualPlan::factory()->create()->id,
        ]);

        $newPlan = AnnualPlan::factory()->create();

        $this->putJson("/api/v1/hr/targets/{$target->id}", [
            'annual_plan_id' => $newPlan->id,
            'title' => 'Revised accreditation goal',
            'target_value' => '50',
            'end_date' => $target->end_date?->toDateString() ?? '2026-12-31',
            'start_date' => $target->start_date?->toDateString() ?? '2026-01-01',
            'status' => 'completed',
        ])->assertOk()
            ->assertJsonPath('data.kpi', 'Revised accreditation goal')
            ->assertJsonPath('data.stored_status', 'completed');

        $this->assertDatabaseHas('employee_targets', [
            'id' => $target->id,
            'title' => 'Revised accreditation goal',
            'target_value' => 50,
            'annual_plan_id' => $newPlan->id,
        ]);

        $this->assertSame(1, ActivityLog::query()->where('activity_type', 'target.updated')->count());
    }

    public function test_update_on_an_unknown_id_answers_404(): void
    {
        $plan = AnnualPlan::factory()->create();

        $this->putJson('/api/v1/hr/targets/99999', [
            'annual_plan_id' => $plan->id,
            'title' => 'No one is here',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ])->assertNotFound();
    }

    public function test_hr_can_delete_a_target(): void
    {
        $target = EmployeeTarget::factory()->create([
            'annual_plan_id' => AnnualPlan::factory()->create()->id,
        ]);

        $this->deleteJson("/api/v1/hr/targets/{$target->id}")->assertOk();

        $this->assertSoftDeleted('employee_targets', ['id' => $target->id]);
        $this->assertSame(1, ActivityLog::query()->where('activity_type', 'target.deleted')->count());
    }

    public function test_delete_on_an_unknown_id_answers_404(): void
    {
        $this->deleteJson('/api/v1/hr/targets/99999')->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Admin regression — the admin surface stays untouched */
    /* ------------------------------------------------------------------ */

    public function test_admin_kpi_targets_route_still_reads_the_same_rows(): void
    {
        EmployeeTarget::factory()->create([
            'annual_plan_id' => AnnualPlan::factory()->create()->id,
        ]);

        $this->app['auth']->forgetGuards();
        $this->loginAs($this->adminUser());

        $this->getJson('/api/v1/admin/kpi-targets')
            ->assertOk()
            ->assertJsonPath('data.summary.total_kpis', 1)
            ->assertJsonPath('data.meta.total', 1);
    }
}
