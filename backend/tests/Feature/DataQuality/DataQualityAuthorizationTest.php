<?php

namespace Tests\Feature\DataQuality;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualityIssueType;
use App\Models\DataQualityIssue;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataQualityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_head_can_only_view_own_department_issues(): void
    {
        $deptA = Department::factory()->create(['name' => 'Department A']);
        $deptB = Department::factory()->create(['name' => 'Department B']);

        $headUser = User::factory()->create();
        $headUser->assignRole('department_head');
        $headEmp = Employee::factory()->create(['user_id' => $headUser->id, 'department_id' => $deptA->id]);

        $empA = Employee::factory()->create(['department_id' => $deptA->id]);
        $empB = Employee::factory()->create(['department_id' => $deptB->id]);

        $issueA = DataQualityIssue::create([
            'issue_type' => DataQualityIssueType::MISSING_REQUIRED_PROFILE_DATA,
            'severity' => DataQualityIssueSeverity::MEDIUM,
            'status' => DataQualityIssueStatus::OPEN,
            'entity_type' => 'employee',
            'entity_id' => $empA->id,
            'department_id' => $deptA->id,
            'employee_id' => $empA->id,
            'title' => 'Dept A Issue',
            'description' => 'Desc A',
            'fingerprint' => DataQualityIssue::generateFingerprint('MISSING_REQUIRED_PROFILE_DATA', 'employee', $empA->id),
        ]);

        $issueB = DataQualityIssue::create([
            'issue_type' => DataQualityIssueType::MISSING_REQUIRED_PROFILE_DATA,
            'severity' => DataQualityIssueSeverity::MEDIUM,
            'status' => DataQualityIssueStatus::OPEN,
            'entity_type' => 'employee',
            'entity_id' => $empB->id,
            'department_id' => $deptB->id,
            'employee_id' => $empB->id,
            'title' => 'Dept B Issue',
            'description' => 'Desc B',
            'fingerprint' => DataQualityIssue::generateFingerprint('MISSING_REQUIRED_PROFILE_DATA', 'employee', $empB->id),
        ]);

        Sanctum::actingAs($headUser);

        $response = $this->getJson('/api/v1/data-quality/issues');

        $response->assertStatus(200);
        $issueIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($issueA->id, $issueIds);
        $this->assertNotContains($issueB->id, $issueIds);
    }
}
