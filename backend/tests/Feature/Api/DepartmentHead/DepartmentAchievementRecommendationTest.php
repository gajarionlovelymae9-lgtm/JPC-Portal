<?php

namespace Tests\Feature\Api\DepartmentHead;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Badge;
use App\Models\Department;
use App\Models\EmployeeBadge;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

/**
 * Contract tests for the Department Head recognition recommendation flow.
 *
 * Recommendations reuse the existing employee_badges award (created as a
 * pending award stamped with the recommending head), so HR's verify/reject
 * workflow reviews them with no second recognition system.
 */
class DepartmentAchievementRecommendationTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /** Direct creation: no BadgeFactory exists in this project's factories. */
    private function createBadge(string $code = 'RECOGNITION_TEST'): Badge
    {
        return Badge::create(['name' => ucwords(str_replace('_', ' ', strtolower($code))), 'code' => $code, 'is_active' => true]);
    }

    private Department $department;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class]);
        $this->department = Department::factory()->create();
        $this->head = $this->departmentHeadUser($this->department);
        $this->loginAs($this->head);
    }

    public function test_head_recommends_own_department_employee(): void
    {
        $nominee = $this->employeeUser($this->department);
        $badge = $this->createBadge('EXCELLENCE_AWARD');
        AcademicYear::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        $response = $this->postJson('/api/v1/department-head/achievements/recommendations', [
            'employee_id' => $nominee->id,
            'badge_id' => $badge->id,
            'details' => 'Outstanding report quality across the whole quarter.',
        ])->assertStatus(201);

        $award = EmployeeBadge::query()->firstOrFail();
        $this->assertSame($nominee->id, $award->employee_id);
        $this->assertSame($badge->id, $award->badge_id);
        $this->assertSame('pending', $award->verification_status);
        $this->assertSame($this->head->id, (int) $award->recommended_by);
        $this->assertSame('Outstanding report quality across the whole quarter.', $award->recommendation_details);

        // Recommendation actions must land in the existing audit trail.
        $this->assertDatabaseHas((new ActivityLog)->getTable(), [
            'activity_type' => 'department_head.achievement.recommend',
            'user_id' => $this->head->id,
        ]);

        $this->assertSame($nominee->id, $response->json('data.employee_id'));
        $this->assertSame('pending', $response->json('data.verification_status'));
    }

    public function test_head_cannot_recommend_an_employee_of_another_department(): void
    {
        $otherDepartment = Department::factory()->create();
        $outsider = $this->employeeUser($otherDepartment);
        $badge = $this->createBadge('CROSS_DEPT');

        $this->postJson('/api/v1/department-head/achievements/recommendations', [
            'employee_id' => $outsider->id,
            'badge_id' => $badge->id,
            'details' => 'Definitely supports the nomination details.',
        ])->assertStatus(403);

        $this->assertDatabaseCount((new EmployeeBadge)->getTable(), 0);
    }

    public function test_head_cannot_recommend_themselves(): void
    {
        // departmentHeadUser() linked this head to an employee record of
        // their own department.
        $headEmployee = $this->head->employee;
        $this->assertNotNull($headEmployee);
        $badge = $this->createBadge();

        $this->postJson('/api/v1/department-head/achievements/recommendations', [
            'employee_id' => $headEmployee->id,
            'badge_id' => $badge->id,
            'details' => 'Self-nominations must be rejected outright.',
        ])->assertStatus(422);

        $this->assertDatabaseCount((new EmployeeBadge)->getTable(), 0);
    }

    public function test_duplicate_recommendation_is_rejected_without_a_second_award(): void
    {
        $nominee = $this->employeeUser($this->department);
        $badge = $this->createBadge();
        AcademicYear::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        $payload = [
            'employee_id' => $nominee->id,
            'badge_id' => $badge->id,
            'details' => 'First recommendation for this employee.',
        ];

        $this->postJson('/api/v1/department-head/achievements/recommendations', $payload)->assertStatus(201);
        $this->postJson('/api/v1/department-head/achievements/recommendations', $payload)->assertStatus(422);

        $this->assertDatabaseCount((new EmployeeBadge)->getTable(), 1);
    }

    public function test_supporting_details_are_required(): void
    {
        $nominee = $this->employeeUser($this->department);
        $badge = $this->createBadge();

        $this->postJson('/api/v1/department-head/achievements/recommendations', [
            'employee_id' => $nominee->id,
            'badge_id' => $badge->id,
            'details' => 'short',
        ])->assertStatus(422);
    }

    public function test_head_tracks_their_own_recommendation_status(): void
    {
        $nominee = $this->employeeUser($this->department);
        $badge = $this->createBadge();
        AcademicYear::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        $this->postJson('/api/v1/department-head/achievements/recommendations', [
            'employee_id' => $nominee->id,
            'badge_id' => $badge->id,
            'details' => 'Consistently early submissions with zero revisions.',
        ])->assertStatus(201);

        $listing = $this->getJson('/api/v1/department-head/achievements/recommendations')->assertOk();

        $this->assertSame(1, count($listing->json('data.recommendations')));
        $this->assertSame($nominee->id, $listing->json('data.recommendations.0.employee_id'));
        $this->assertSame('pending', $listing->json('data.recommendations.0.verification_status'));

        // Another department's head sees none of it.
        $otherHead = $this->departmentHeadUser(Department::factory()->create());
        $this->loginAs($otherHead);
        $otherListing = $this->getJson('/api/v1/department-head/achievements/recommendations')->assertOk();

        $this->assertSame(0, count($otherListing->json('data.recommendations')));
    }

    public function test_hr_verifies_a_recommendation_and_the_head_sees_the_result(): void
    {
        $nominee = $this->employeeUser($this->department);
        $badge = $this->createBadge();
        AcademicYear::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        $id = $this->postJson('/api/v1/department-head/achievements/recommendations', [
            'employee_id' => $nominee->id,
            'badge_id' => $badge->id,
            'details' => 'Excellent customer-impact project delivery.',
        ])->json('data.id');

        $hr = $this->hrUser();
        $this->loginAs($hr);
        $this->postJson("/api/v1/hr/achievement-badges/{$id}/verify")->assertOk();

        $this->loginAs($this->head);
        $this->assertSame(
            'verified',
            $this->getJson('/api/v1/department-head/achievements/recommendations')
                ->json('data.recommendations.0.verification_status'),
        );
    }

    public function test_rejection_carries_the_hr_reason_back_to_the_head(): void
    {
        $nominee = $this->employeeUser($this->department);
        $badge = $this->createBadge();
        AcademicYear::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        $id = $this->postJson('/api/v1/department-head/achievements/recommendations', [
            'employee_id' => $nominee->id,
            'badge_id' => $badge->id,
            'details' => 'Reasonable but not enough supporting evidence.',
        ])->json('data.id');

        $hr = $this->hrUser();
        $this->loginAs($hr);
        $this->postJson("/api/v1/hr/achievement-badges/{$id}/reject", [
            'reason' => 'Insufficient evidence for a tier-2 award.',
        ])->assertOk();

        $this->loginAs($this->head);
        $row = $this->getJson('/api/v1/department-head/achievements/recommendations')->json('data.recommendations.0');

        $this->assertSame('rejected', $row['verification_status']);
        $this->assertSame('Insufficient evidence for a tier-2 award.', $row['rejection_reason']);
    }

    public function test_guest_cannot_recommend(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/department-head/achievements/recommendations')->assertStatus(401);
    }
}
