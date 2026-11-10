<?php

namespace Tests\Feature\Api\Performance;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Badge;
use App\Models\Department;
use App\Models\EmployeeBadge;
use App\Models\MonthlyAchievement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the employee badge-progress endpoint
 * (GET /api/v1/achievements/progress).
 *
 * Employees see their own earned badges plus tracked progress toward every
 * unearned badge in the catalogue — never another employee's records.
 */
class AchievementProgressApiTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
    }

    public function test_progress_reflects_recorded_monthly_statistics(): void
    {
        $department = Department::factory()->create();
        $user = $this->roleUser(Role::Employee);
        $employee = $this->employeeUser($department, $user);
        // Use this test's own academic year ID: other tests in a full-suite
        // run can leave the sequences shifted, and the FK is ON DELETE RESTRICT.
        $year = AcademicYear::factory()->create();

        $taskChampion = Badge::create([
            'name' => 'Task Master',
            'code' => 'TASK_CHAMPION',
            'criteria_type' => null,
            'is_active' => true,
        ]);
        $onTime = Badge::create([
            'name' => 'On-Time Champion',
            'code' => 'ON_TIME_HERO',
            'is_active' => true,
        ]);
        Badge::create(['name' => 'Monthly Achiever', 'code' => 'EMPLOYEE_OF_MONTH', 'is_active' => true]);

        // Earned badge: Task Master for last month.
        EmployeeBadge::forceCreate([
            'employee_id' => $employee->id,
            'badge_id' => $taskChampion->id,
            'academic_year_id' => $year->id,
            'awarded_at' => now(),
            'criteria_snapshot' => ['completed_tasks' => 12],
            'verification_status' => 'verified',
        ]);

        // Real recorded rollups: 80% on-time so On-Time Hero stays in progress.
        MonthlyAchievement::forceCreate([
            'employee_id' => $employee->id,
            'academic_year_id' => $year->id,
            'month' => now()->month,
            'year' => now()->year,
        ])->forceFill([
            'on_time_rate' => 80.0,
            'completion_rate' => 90.0,
            'overall_score' => 88.0,
            'approved_reports' => 2,
            'completed_tasks' => 4,
        ])->save();

        $this->loginAs($user);
        $response = $this->getJson('/api/v1/achievements/progress')->assertOk();

        $badges = collect($response->json('data.badges'))->keyBy('code');

        $this->assertTrue($badges->get('TASK_CHAMPION')['earned']);
        $this->assertSame(100, (int) $badges->get('TASK_CHAMPION')['progress']);
        $this->assertSame(now()->toDateString(), $badges->get('TASK_CHAMPION')['award_dates'][0]);

        $this->assertFalse($badges->get('ON_TIME_HERO')['earned']);
        $this->assertSame(80.0, (float) $badges->get('ON_TIME_HERO')['progress']);
        $this->assertSame(80.0, (float) $badges->get('ON_TIME_HERO')['current_value']);
        $this->assertSame(100, (int) $badges->get('ON_TIME_HERO')['target_value']);
        $this->assertNotEmpty($badges->get('ON_TIME_HERO')['criteria']);
    }

    public function test_employee_only_reaches_their_own_progress(): void
    {
        // A user without a linked employee record cannot reach the endpoint.
        $plainUser = $this->roleUser(Role::Employee);
        $this->loginAs($plainUser);
        $this->getJson('/api/v1/achievements/progress')->assertStatus(404);
    }

    public function test_guest_is_rejected(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/achievements/progress')->assertStatus(401);
    }
}
