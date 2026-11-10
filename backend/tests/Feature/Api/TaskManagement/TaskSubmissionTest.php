<?php

namespace Tests\Feature\Api\TaskManagement;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Role as RoleModel;
use App\Models\TaskAssignment;
use App\Models\TaskAttachment;
use App\Models\TaskSubmission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class]);
        Storage::fake('local');
    }

    private function userWithRole(Role $role): User
    {
        $roleModel = RoleModel::query()->where('name', $role->value)->firstOrFail();

        return User::factory()->create(['role_id' => $roleModel->id]);
    }

    private function employeeUser(): User
    {
        $user = $this->userWithRole(Role::Employee);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function assignmentFor(User $user, array $attributes = []): TaskAssignment
    {
        $employee = Employee::query()->where('user_id', $user->id)->firstOrFail();

        return TaskAssignment::factory()->create(
            array_merge(['employee_id' => $employee->id, 'status' => 'in_progress'], $attributes)
        );
    }

    private function login(User $user): void
    {
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs($user, 'sanctum');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function multipart(TaskAssignment $assignment, array $payload, string $action = 'submit')
    {
        return $this->post(
            "/api/v1/task-assignments/{$assignment->id}/{$action}",
            $payload,
            ['Accept' => 'application/json'],
        );
    }

    // ── METHOD: UPLOAD FILES ───────────────────────────────────

    public function test_employee_can_submit_files_with_metadata_and_secure_storage(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $response = $this->multipart($assignment, [
            'method' => 'upload',
            'notes' => 'Quarterly report attached.',
            'files' => [
                UploadedFile::fake()->create('report.pdf', 250, 'application/pdf'),
                UploadedFile::fake()->create('data.xlsx', 120, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.submissions.0.method', 'upload')
            ->assertJsonPath('data.submissions.0.notes', 'Quarterly report attached.')
            ->assertJsonCount(2, 'data.submissions.0.attachments');

        $required = [
            'application/pdf' => 'report.pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'data.xlsx',
        ];
        foreach ($required as $mime => $name) {
            $this->assertDatabaseHas('task_attachments', [
                'task_assignment_id' => $assignment->id,
                'file_name' => $name,
                'mime_type' => $mime,
            ]);
        }

        $attachment = TaskAttachment::query()->firstOrFail();
        $this->assertStringStartsWith('task-submissions/'.$assignment->id.'/', $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_upload_rejects_disallowed_extension_and_mime(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->multipart($assignment, [
            'method' => 'upload',
            'files' => [UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')],
        ])->assertStatus(422)->assertJsonValidationErrors(['files.0']);
    }

    public function test_upload_rejects_files_over_the_size_limit(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->multipart($assignment, [
            'method' => 'upload',
            'files' => [UploadedFile::fake()->create('huge.pdf', 30 * 1024, 'application/pdf')],
        ])->assertStatus(422)->assertJsonValidationErrors(['files.0']);
    }

    // ── METHOD: LINK ───────────────────────────────────────────

    public function test_employee_can_submit_an_https_link(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $url = 'https://drive.google.com/file/d/abc123/view';

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'link',
            'url' => $url,
            'notes' => 'Shared folder link.',
        ])
            ->assertOk()
            ->assertJsonPath('data.submissions.0.method', 'link')
            ->assertJsonPath('data.submissions.0.url', $url);

        $this->assertDatabaseHas('task_submissions', [
            'task_assignment_id' => $assignment->id,
            'url' => $url,
        ]);
    }

    public function test_link_submission_rejects_non_https_and_unsafe_schemes(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        foreach (['http://drive.google.com/file/1', 'javascript:alert(1)', 'ftp://example.com/file'] as $url) {
            $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
                'method' => 'link',
                'url' => $url,
            ])->assertStatus(422)->assertJsonValidationErrors(['url']);
        }

        $this->assertSame(0, TaskSubmission::query()->count());
    }

    public function test_link_submission_requires_a_url(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'link',
        ])->assertStatus(422)->assertJsonValidationErrors(['method']);
    }

    // ── METHOD: FOLDER ─────────────────────────────────────────

    public function test_employee_can_submit_a_zip_folder_archive(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->multipart($assignment, [
            'method' => 'folder',
            'files' => [UploadedFile::fake()->create('deliverables.zip', 512, 'application/zip')],
        ])
            ->assertOk()
            ->assertJsonPath('data.submissions.0.method', 'folder')
            ->assertJsonCount(1, 'data.submissions.0.attachments');
    }

    public function test_employee_can_submit_a_shared_folder_url(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'folder',
            'url' => 'https://1drv.ms/f/s!folder-token',
        ])
            ->assertOk()
            ->assertJsonPath('data.submissions.0.url', 'https://1drv.ms/f/s!folder-token');
    }

    public function test_folder_submission_rejects_non_zip_upload(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->multipart($assignment, [
            'method' => 'folder',
            'files' => [UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf')],
        ])->assertStatus(422)->assertJsonValidationErrors(['files.0']);
    }

    public function test_folder_submission_requires_a_zip_or_url(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'folder',
        ])->assertStatus(422)->assertJsonValidationErrors(['method']);
    }

    // ── METHOD: WRITTEN RESPONSE ───────────────────────────────

    public function test_employee_can_submit_a_written_response(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'written',
            'written_response' => 'Completed all faculty reports and filed them with the department.',
            'notes' => 'See narrative.',
        ])
            ->assertOk()
            ->assertJsonPath('data.submissions.0.method', 'written')
            ->assertJsonPath('data.submissions.0.written_response', 'Completed all faculty reports and filed them with the department.');
    }

    public function test_written_submission_requires_content(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'written',
        ])->assertStatus(422)->assertJsonValidationErrors(['method']);
    }

    public function test_submission_requires_a_valid_method(): void
    {
        $user = $this->employeeUser();
        $this->login($user);
        $assignment = $this->assignmentFor($user);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['method']);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", ['method' => 'email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['method']);
    }

    // ── VERSIONING & REVIEWER VISIBILITY ───────────────────────

    public function test_resubmission_preserves_every_version_and_review_feedback(): void
    {
        $employee = $this->employeeUser();
        $admin = $this->userWithRole(Role::Admin);
        $assignment = $this->assignmentFor($employee);

        $this->login($employee);
        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'written',
            'written_response' => 'Version one.',
        ])->assertOk();

        $this->login($admin);
        $this->postJson("/api/v1/task-assignments/{$assignment->id}/review", [
            'decision' => 'needs_revision',
            'remarks' => 'Please include the summary table.',
        ])->assertOk();

        $this->login($employee);
        $this->postJson("/api/v1/task-assignments/{$assignment->id}/resubmit", [
            'method' => 'written',
            'written_response' => 'Version two with the summary table.',
        ])->assertOk();

        $this->assertSame(2, TaskSubmission::query()->where('task_assignment_id', $assignment->id)->count());

        $this->login($admin);
        $this->getJson("/api/v1/task-assignments/{$assignment->id}/submissions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.0.written_response', 'Version two with the summary table.')
            ->assertJsonPath('data.1.version', 1)
            ->assertJsonPath('data.1.written_response', 'Version one.');

        $this->assertDatabaseHas('task_reviews', [
            'task_assignment_id' => $assignment->id,
            'decision' => 'needs_revision',
            'remarks' => 'Please include the summary table.',
        ]);
    }

    public function test_reviewer_can_see_files_links_and_notes_when_loading_the_task(): void
    {
        $employee = $this->employeeUser();
        $admin = $this->userWithRole(Role::Admin);
        $assignment = $this->assignmentFor($employee);

        $this->login($employee);
        $this->multipart($assignment, [
            'method' => 'upload',
            'notes' => 'Reviewer note.',
            'files' => [UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf')],
        ])->assertOk();

        $this->login($admin);
        $this->getJson("/api/v1/tasks/{$assignment->task_id}")
            ->assertOk()
            ->assertJsonPath('data.assignments.0.submissions.0.notes', 'Reviewer note.')
            ->assertJsonPath('data.assignments.0.submissions.0.attachments.0.file_name', 'evidence.pdf');
    }

    public function test_employee_can_download_own_attachment_but_other_employees_cannot(): void
    {
        $owner = $this->employeeUser();
        $other = $this->employeeUser();
        $admin = $this->userWithRole(Role::Admin);
        $assignment = $this->assignmentFor($owner);

        $this->login($owner);
        $this->multipart($assignment, [
            'method' => 'upload',
            'files' => [UploadedFile::fake()->create('private.pdf', 20, 'application/pdf')],
        ])->assertOk();

        $attachment = TaskAttachment::query()->firstOrFail();
        $url = "/api/v1/task-attachments/{$attachment->id}/download";

        $this->get($url, ['Accept' => 'application/json'])->assertOk();

        $this->login($other);
        $this->get($url, ['Accept' => 'application/json'])->assertForbidden();

        $this->login($admin);
        $this->get($url, ['Accept' => 'application/json'])->assertOk();
    }

    public function test_other_employee_cannot_submit_or_read_history_for_someone_elses_assignment(): void
    {
        $owner = $this->employeeUser();
        $other = $this->employeeUser();
        $assignment = $this->assignmentFor($owner);

        $this->login($other);

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'written',
            'written_response' => 'Should never be stored.',
        ])->assertForbidden();

        $this->getJson("/api/v1/task-assignments/{$assignment->id}/submissions")
            ->assertForbidden();

        $this->assertSame(0, TaskSubmission::query()->count());
    }

    public function test_employee_cannot_review_their_own_submission(): void
    {
        $employee = $this->employeeUser();
        $assignment = $this->assignmentFor($employee);

        $this->login($employee);
        $this->postJson("/api/v1/task-assignments/{$assignment->id}/submit", [
            'method' => 'written',
            'written_response' => 'My work.',
        ])->assertOk();

        $this->postJson("/api/v1/task-assignments/{$assignment->id}/review", [
            'decision' => 'approved',
            'remarks' => 'Self approval.',
        ])->assertForbidden();
    }
}
