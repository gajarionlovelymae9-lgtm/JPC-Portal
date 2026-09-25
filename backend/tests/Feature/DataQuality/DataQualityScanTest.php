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
                ]
            ]);
    }
}
