<?php

namespace Tests\Feature\Api\Dashboard;

use App\Enums\Role;
use App\Models\AiAnalysis;
use App\Models\AiSummary;
use App\Models\AiValidationResult;
use App\Models\Department;
use App\Models\DepartmentHead;
use App\Models\Employee;
use App\Models\OcrProcessing;
use App\Models\Report;
use App\Models\ReportFile;
use App\Models\Role as RoleModel;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the defects surfaced by the Phase 14 full demo
 * E2E run (admin/hr/department-head/employee personas):
 *   1. Admin executive dashboard (phantom task `deadline` column).
 *   2. Admin departments (undefined `head` relationship).
 *   3. HR employees payload (ApiResponse::collection on a Collection).
 *   4. Department-head tasks (undefined `creator` relationship).
 *   5. Department-head + employee report detail (phantom singular AI/OCR
 *      relationships, `report_versions.version`, `ocr_processings.report_id`).
 *   6. AI insights (hasRole() given an array).
 *   7. Messaging recipients / announcements (phantom `users.is_active`).
 *   8. Department escalations (UTF-8 BOM breaking route/model parsing).
 */
class PortalSurfaceRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
    }

    private function roleId(Role $role): int
    {
        return RoleModel::query()->where('name', $role->value)->firstOrFail()->id;
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Admin)]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function hr(): User
    {
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Hr)]);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function departmentHead(): User
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
        DepartmentHead::create([
            'department_id' => $department->id,
            'employee_id' => $head->id,
            'start_date' => now()->subYear()->toDateString(),
            'is_current' => true,
        ]);

        $task = Task::factory()->create(['department_id' => $department->id, 'status' => 'in_progress']);
        $emp = Employee::factory()->create(['department_id' => $department->id]);
        TaskAssignment::factory()->create([
            'employee_id' => $emp->id,
            'task_id' => $task->id,
            'progress_percentage' => 40,
            'due_date' => now()->addDays(5),
            'status' => 'in_progress',
        ]);
        $report = $this->report($emp, $task);

        return $user;
    }

    private function employeeUser(): User
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        $emp = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
        $task = Task::factory()->create(['department_id' => $department->id, 'status' => 'in_progress']);
        TaskAssignment::factory()->create([
            'employee_id' => $emp->id,
            'task_id' => $task->id,
            'progress_percentage' => 20,
            'due_date' => now()->addDays(3),
            'status' => 'in_progress',
        ]);
        $this->report($emp, $task);

        return $user;
    }

    private function report(Employee $emp, Task $task): Report
    {
        $report = Report::factory()->create([
            'employee_id' => $emp->id,
            'task_id' => $task->id,
            'status' => 'under_review',
            'submitted_at' => now()->subDay(),
        ]);

        $version = $report->versions()->create([
            'version_number' => 1,
            'submitted_by' => $emp->user_id,
            'notes' => 'Initial submission',
            'submitted_at' => now()->subDay(),
        ]);

        $file = ReportFile::factory()->create([
            'report_id' => $report->id,
            'report_version_id' => $version->id,
            'uploaded_by' => $emp->user_id,
        ]);

        OcrProcessing::factory()->create([
            'report_file_id' => $file->id,
            'status' => 'completed',
            'raw_text' => 'Extracted accomplishment report text for regression assertions.',
            'confidence_score' => 0.92,
        ]);

        $analysis = AiAnalysis::factory()->summarization()->create(['report_id' => $report->id]);

        AiSummary::factory()->create([
            'report_id' => $report->id,
            'ai_analysis_id' => $analysis->id,
            'summary' => json_encode(['executive_summary' => 'Executive summary from regression fixture.']),
            'key_findings' => ['Delivered all major deliverables'],
            'key_accomplishments' => ['Completed primary objective'],
        ]);

        AiValidationResult::factory()->create([
            'report_id' => $report->id,
            'ai_analysis_id' => $analysis->id,
            'result' => 'pass',
        ]);

        return $report;
    }

    public function test_admin_can_load_executive_dashboard(): void
    {
        $this->login($this->admin());

        $this->getJson('/api/v1/admin/executive-dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['kpis' => [], 'status_distribution' => []]]);
    }

    public function test_admin_can_load_departments(): void
    {
        $this->login($this->admin());

        $this->getJson('/api/v1/admin/departments')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_hr_can_load_employees_payload(): void
    {
        $this->login($this->hr());

        $this->getJson('/api/v1/hr/employees')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_department_head_can_load_tasks(): void
    {
        $this->login($this->departmentHead());

        $this->getJson('/api/v1/department-head/tasks')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_department_head_can_load_report_detail(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['role_id' => $this->roleId(Role::DepartmentHead)]);
        $head = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
        DepartmentHead::create([
            'department_id' => $department->id,
            'employee_id' => $head->id,
            'start_date' => now()->subYear()->toDateString(),
            'is_current' => true,
        ]);

        $task = Task::factory()->create(['department_id' => $department->id]);
        $emp = Employee::factory()->create(['department_id' => $department->id]);
        $report = $this->report($emp, $task);

        $this->login($user);

        $this->getJson("/api/v1/department-head/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'task', 'ocr' => ['extracted_text'], 'ai_summary' => ['executive_summary', 'key_points'],
                'ai_validation' => ['requirement_checks'], 'files', 'reviews',
            ]]);
    }

    public function test_employee_can_load_report_detail_with_versions(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['role_id' => $this->roleId(Role::Employee)]);
        $emp = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
        $task = Task::factory()->create(['department_id' => $department->id]);
        $report = $this->report($emp, $task);
        $report->versions()->create([
            'version_number' => 2,
            'submitted_by' => $emp->user_id,
            'notes' => 'Revised after feedback',
            'submitted_at' => now(),
        ]);

        $this->login($user);

        $this->getJson("/api/v1/employee/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['versions', 'ocr', 'ai_summary', 'review_history']]);
    }

    public function test_ai_insights_load_without_has_role_error(): void
    {
        $employee = $this->employeeUser();

        $this->login($employee);

        $this->getJson('/api/v1/ai/insights')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/ai/insights/performance')->assertOk();
        $this->getJson('/api/v1/ai/insights/reports')->assertOk();
    }

    public function test_messaging_recipients_load_without_is_active_error(): void
    {
        $this->login($this->employeeUser());

        $this->getJson('/api/v1/messages/recipients')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_department_head_can_load_escalations(): void
    {
        $this->login($this->departmentHead());

        $this->getJson('/api/v1/department-head/escalations')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_load_escalations(): void
    {
        $this->login($this->admin());

        $this->getJson('/api/v1/admin/escalations')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}