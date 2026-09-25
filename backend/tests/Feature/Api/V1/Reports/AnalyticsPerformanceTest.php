<?php

namespace Tests\Feature\Api\V1\Reports;

use App\Models\User;
use App\Services\Reports\PerformanceCalculationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @covers App\Http\Controllers\Api\V1\Reports\ReportController
 * @covers App\Services\Reports\PerformanceCalculationService
 */
class AnalyticsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsUserWith(string $permission): User
    {
        $role = \App\Models\Role::query()
            ->where('name', \App\Enums\Role::Admin->value)
            ->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_overview_returns_200_for_authorized_user(): void
    {
        $user = $this->actingAsUserWith('analytics.view');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/overview');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);
    }

    public function test_overview_returns_403_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/overview');

        $response->assertStatus(403);
    }

    public function test_leaderboard_returns_200_for_authorized_user(): void
    {
        $user = $this->actingAsUserWith('analytics.view');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/leaderboard');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);
    }

    public function test_leaderboard_returns_403_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/reports/leaderboard');

        $response->assertStatus(403);
    }

    public function test_performance_calculation_service_computes_score(): void
    {
        $service = new PerformanceCalculationService();

        $record = new \App\Models\EmployeePerformanceRecord([
            'completion_rate' => 85.0,
            'on_time_rate' => 90.0,
            'approval_rate' => 95.0,
            'target_achievement_rate' => 80.0,
            'overall_score' => 87.5,
        ]);

        $weights = [
            'completion_rate' => 0.3,
            'on_time_rate' => 0.25,
            'approval_rate' => 0.2,
            'target_achievement_rate' => 0.25,
        ];

        $score = $service->calculateScore($record, $weights);

        $this->assertIsFloat($score);
        $this->assertEquals(87.0, $score);
    }
}
