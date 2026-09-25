<?php

namespace Tests\Feature\Api\V1\Reports;

use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Report;
use App\Models\ReportReviewComment;
use App\Models\ReportVersion;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deptHead;
    protected User $employeeUser;
    protected Employee $employee;
    protected Department $department;
    protected Task $task;
    protected TaskAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $adminRole = \App\Models\Role::where('name', 'admin')->firstOrFail();
        $deptHeadRole = \App\Models\Role::where('name', 'department_head')->firstOrFail();
        $employeeRole = \App\Models\Role::where('name', 'employee')->firstOrFail();

        $this->department = Department::create([
            'name' => 'College of Computer Studies',
            'code' => 'CCS',
            'status' => 'active',
        ]);

        $position = Position::create([
            'department_id' => $this->department->id,
            'name' => 'Instructor I',
            'code' => 'INST-1',
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->deptHead = User::factory()->create([
            'role_id' => $deptHeadRole->id,
        ]);

        $this->employeeUser = User::factory()->create([
            'role_id' => $employeeRole->id,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'department_id' => $this->department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-2026-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'employment_status' => 'active',
        ]);

        $this->task = Task::create([
            'department_id' => $this->department->id,
            'created_by' => $this->deptHead->id,
            'title' => 'Syllabus Preparation CS101',
            'task_code' => 'TSK-2026-001',
            'status' => 'in_progress',
            'priority' => 'high',
            'deadline' => now()->addDays(5),
            'requirements' => 'Submit updated syllabus according to CHED CMO 25',
        ]);

        $this->assignment = TaskAssignment::create([
            'task_id' => $this->task->id,
            'employee_id' => $this->employee->id,
            'status' => 'in_progress',
            'progress_percentage' => 0,
            'assigned_at' => now(),
            'due_date' => now()->addDays(5),
        ]);
    }

    public function test_employee_can_create_and_update_draft_report(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/reports', [
            'task_id' => $this->task->id,
            'title' => 'CS101 Syllabus Draft',
            'content' => 'Course syllabus initial draft according to curriculum guidelines.',
            'references' => 'CHED CMO 25 Series 2015',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'CS101 Syllabus Draft')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1);

        $reportId = $response->json('data.id');

        // Update draft
        $updateResponse = $this->putJson("/api/v1/reports/{$reportId}", [
            'title' => 'CS101 Syllabus Updated Draft',
            'content' => 'Added laboratory modules and grading system.',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'CS101 Syllabus Updated Draft');
    }

    public function test_employee_submits_report_and_triggers_state_transition(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $createRes = $this->postJson('/api/v1/reports', [
            'task_id' => $this->task->id,
            'title' => 'CS101 Syllabus Final Draft',
            'content' => 'Full syllabus document with 18-week schedule.',
        ]);

        $reportId = $createRes->json('data.id');
        $report = Report::find($reportId);

        $submitRes = $this->postJson("/api/v1/reports/{$reportId}/submit", [
            'notes' => 'Ready for departmental review.',
        ]);

        $submitRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $report->refresh();
        $this->assertEquals(ReportStatus::UnderReview, $report->status);
        $this->assertNotNull($report->submitted_at);

        // Check snapshot was created
        $this->assertDatabaseHas('report_versions', [
            'report_id' => $reportId,
            'version_number' => 1,
            'submitted_by' => $this->employeeUser->id,
        ]);
    }

    public function test_reviewer_can_add_comments_and_request_revision(): void
    {
        Sanctum::actingAs($this->employeeUser);
        $createRes = $this->postJson('/api/v1/reports', [
            'task_id' => $this->task->id,
            'title' => 'CS101 Syllabus v1',
            'content' => 'Initial submission for evaluation.',
        ]);
        $reportId = $createRes->json('data.id');
        $this->postJson("/api/v1/reports/{$reportId}/submit");

        // Dept Head reviews
        Sanctum::actingAs($this->deptHead);

        // Add inline comment
        $commentRes = $this->postJson("/api/v1/reports/{$reportId}/comments", [
            'section_key' => 'grading_rubric',
            'comment' => 'Please detail the midterm exam breakdown.',
        ]);

        $commentRes->assertStatus(201)
            ->assertJsonPath('data.section_key', 'grading_rubric');

        $commentId = $commentRes->json('data.id');

        // Request Revision
        $revReqRes = $this->postJson("/api/v1/reports/{$reportId}/request-revision", [
            'reason' => 'Grading criteria and week 9 lab exercises need revision.',
        ]);

        $revReqRes->assertStatus(200)
            ->assertJsonPath('data.status', 'revision_required');

        // Employee resolves comment and submits revision (v2)
        Sanctum::actingAs($this->employeeUser);

        $resolveRes = $this->postJson("/api/v1/reports/{$reportId}/comments/{$commentId}/resolve");
        $resolveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved');

        $revisionRes = $this->postJson("/api/v1/reports/{$reportId}/revision", [
            'title' => 'CS101 Syllabus v2 (Revised)',
            'content' => 'Detailed midterm breakdown added and week 9 lab schedule adjusted.',
            'change_summary' => 'Added grading rubric and updated lab timeline.',
        ]);

        $revisionRes->assertStatus(200)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', 'under_review');

        // Check version 1 and 2 exist
        $this->assertDatabaseHas('report_versions', ['report_id' => $reportId, 'version_number' => 1]);
        $this->assertDatabaseHas('report_versions', ['report_id' => $reportId, 'version_number' => 2]);

        // Version comparison
        $compareRes = $this->getJson("/api/v1/reports/{$reportId}/versions-compare?from=1&to=2");
        $compareRes->assertStatus(200)
            ->assertJsonPath('data.changes.content_changed', true)
            ->assertJsonPath('data.to_version.version_number', 2);
    }

    public function test_reviewer_approval_updates_task_and_downstream_state(): void
    {
        Sanctum::actingAs($this->employeeUser);
        $createRes = $this->postJson('/api/v1/reports', [
            'task_id' => $this->task->id,
            'title' => 'CS101 Syllabus Completed',
            'content' => 'Comprehensive approved format.',
        ]);
        $reportId = $createRes->json('data.id');
        $this->postJson("/api/v1/reports/{$reportId}/submit");

        // Dept Head approves
        Sanctum::actingAs($this->deptHead);
        $approveRes = $this->postJson("/api/v1/reports/{$reportId}/approve", [
            'comments' => 'Excellent work, all criteria fulfilled.',
        ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Check task assignment completed
        $this->assignment->refresh();
        $this->assertEquals('completed', $this->assignment->status);
        $this->assertEquals(100, (int) $this->assignment->progress_percentage);
        $this->assertNotNull($this->assignment->completed_at);

        // Timeline check
        $timelineRes = $this->getJson("/api/v1/reports/{$reportId}/timeline");
        $timelineRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
