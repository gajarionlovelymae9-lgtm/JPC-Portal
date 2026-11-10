<?php

namespace Tests\Feature\DataQuality;

use App\Enums\DataQualityScanStatus;
use App\Models\DataQualityScan;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataQualityScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_trigger_full_scan(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $dept = Department::factory()->create();
        Employee::factory()->create([
            'department_id' => $dept->id,
            'first_name' => '', // triggers missing required field
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/data-quality/scans', [
            'scan_type' => 'full',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', DataQualityScanStatus::COMPLETED->value);

        $this->assertDatabaseHas('data_quality_scans', [
            'status' => DataQualityScanStatus::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('data_quality_issues', [
            'issue_type' => 'MISSING_REQUIRED_PROFILE_DATA',
        ]);
    }

    public function test_dashboard_endpoint_returns_health_score_and_metrics(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/data-quality/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'score',
                    'total_issues',
                    'open_issues',
                    'critical',
                    'high',
                    'medium',
                    'low',
                    'resolved',
                    'by_category',
                    'by_department',
                    'recent_scans',
                    'trends',
                ],
            ]);
    }

    public function test_scan_index_clamps_an_oversized_per_page_to_the_shared_ceiling(): void
    {
        // The scan ledger has no FormRequest, so `per_page` is not validated:
        // the controller must clamp it itself, or `?per_page=5000` would pull
        // the whole table in one page (the same class of bug as the report
        // endpoints' "per page field must not be greater than 100").
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        DataQualityScan::create([
            'scan_type' => 'full',
            'status' => DataQualityScanStatus::COMPLETED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'records_checked' => 10,
            'issues_found' => 0,
            'critical_count' => 0,
            'high_count' => 0,
            'medium_count' => 0,
            'low_count' => 0,
            'initiated_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/data-quality/scans?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);

        $this->getJson('/api/v1/data-quality/scans?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1);
    }
}
