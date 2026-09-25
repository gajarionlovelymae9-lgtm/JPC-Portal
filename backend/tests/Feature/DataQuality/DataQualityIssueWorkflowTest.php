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

class DataQualityIssueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_review_and_resolution_workflow(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $dept = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $dept->id]);

        $issue = DataQualityIssue::create([
            'issue_type' => DataQualityIssueType::MISSING_REQUIRED_PROFILE_DATA,
            'severity' => DataQualityIssueSeverity::HIGH,
            'status' => DataQualityIssueStatus::OPEN,
            'entity_type' => 'employee',
            'entity_id' => $employee->id,
            'department_id' => $dept->id,
            'employee_id' => $employee->id,
            'title' => 'Missing profile info',
            'description' => 'First name missing',
            'fingerprint' => DataQualityIssue::generateFingerprint('MISSING_REQUIRED_PROFILE_DATA', 'employee', $employee->id),
        ]);

        Sanctum::actingAs($admin);

        // 1. Mark Under Review
        $reviewRes = $this->postJson("/api/v1/data-quality/issues/{$issue->id}/review", [
            'notes' => 'Investigating missing employee fields',
        ]);
        $reviewRes->assertStatus(200)
            ->assertJsonPath('data.status', DataQualityIssueStatus::UNDER_REVIEW->value);

        // 2. Confirm defect
        $confirmRes = $this->postJson("/api/v1/data-quality/issues/{$issue->id}/confirm", [
            'notes' => 'Confirmed profile data is missing',
        ]);
        $confirmRes->assertStatus(200)
            ->assertJsonPath('data.status', DataQualityIssueStatus::CONFIRMED->value);

        // 3. Resolve defect
        $resolveRes = $this->postJson("/api/v1/data-quality/issues/{$issue->id}/resolve", [
            'notes' => 'Updated employee record with required first name and verified identity',
        ]);
        $resolveRes->assertStatus(200)
            ->assertJsonPath('data.status', DataQualityIssueStatus::RESOLVED->value);

        // Verify history records
        $this->assertDatabaseHas('data_quality_issue_histories', [
            'data_quality_issue_id' => $issue->id,
            'new_status' => DataQualityIssueStatus::RESOLVED->value,
            'action' => 'resolved',
        ]);
    }

    public function test_dismiss_false_positive_stores_explanation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $dept = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $dept->id]);

        $issue = DataQualityIssue::create([
            'issue_type' => DataQualityIssueType::DUPLICATE_EMPLOYEE,
            'severity' => DataQualityIssueSeverity::CRITICAL,
            'status' => DataQualityIssueStatus::OPEN,
            'entity_type' => 'employee',
            'entity_id' => $employee->id,
            'department_id' => $dept->id,
            'employee_id' => $employee->id,
            'title' => 'Potential duplicate employee name',
            'description' => 'Same name found in another department',
            'fingerprint' => DataQualityIssue::generateFingerprint('DUPLICATE_EMPLOYEE', 'employee', $employee->id),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/data-quality/issues/{$issue->id}/dismiss", [
            'reason' => 'False positive. Two distinct individuals who coincidentally share identical names.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DataQualityIssueStatus::DISMISSED->value);

        $this->assertDatabaseHas('data_quality_issue_histories', [
            'data_quality_issue_id' => $issue->id,
            'action' => 'dismissed',
        ]);
    }
}
