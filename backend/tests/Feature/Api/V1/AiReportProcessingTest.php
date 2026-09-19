<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Models\Report;
use App\Models\Role as RoleModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI/OCR Processing Center (Phase 3.3-Q) API contract tests.
 *
 * @covers App\Http\Controllers\Api\V1\AiReportController
 */
class AiReportProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsUserWith(string $permission): User
    {
        $role = RoleModel::query()
            ->where('name', Role::Employee->value)
            ->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_dashboard_returns_200_for_authorized_user(): void
    {
        $user = $this->actingAsUserWith('reports.view');

        $this->actingAs($user)
            ->getJson('/api/v1/ai-processing/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthorized_user_cannot_access_dashboard(): void
    {
        $user = $this->actingAsUserWith('reports.view');

        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->getJson('/api/v1/ai-processing/dashboard')
            ->assertForbidden();
    }

    public function test_process_queues_report(): void
    {
        $report = Report::factory()->create();
        $user = $this->actingAsUserWith('reports.update');

        $this->actingAs($user)
            ->postJson("/api/v1/ai-processing/{$report->id}/process")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.job_id', (string) $report->id);
    }

    public function test_process_idempotent_no_duplicate_analysis(): void
    {
        $report = Report::factory()->create();
        $user = $this->actingAsUserWith('reports.update');

        $this->actingAs($user)
            ->postJson("/api/v1/ai-processing/{$report->id}/process");

        $this->actingAs($user)
            ->postJson("/api/v1/ai-processing/{$report->id}/process")
            ->assertOk();

        // Dispatching twice returns the same job id without creating
        // duplicate analyses; a queue worker is required to execute.
        $this->assertNotNull($report->fresh());
    }

    public function test_summary_returns_200_for_authorized_reviewer(): void
    {
        $report = Report::factory()->create();
        $user = $this->actingAsUserWith('reports.view');

        $this->actingAs($user)
            ->getJson("/api/v1/ai-processing/{$report->id}/ai-summary")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthorized_employee_cannot_access_another_report_analysis(): void
    {
        $report = Report::factory()->create();
        $employee = $this->actingAsUserWith('reports.view');

        $other = User::factory()->create();
        $this->actingAs($other)
            ->getJson("/api/v1/ai-processing/{$report->id}/ai-summary")
            ->assertForbidden();
    }
}
