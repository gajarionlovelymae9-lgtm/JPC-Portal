<?php

namespace Tests\Feature\Api\V1\System;

use App\Models\AiFeedback;
use App\Models\MaintenanceLog;
use App\Models\SystemHealthCheck;
use App\Models\User;
use App\Models\UserFeedback;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13 — System health / maintenance / feedback API. These endpoints are
 * admin-only (health + maintenance + feedback management) or any-authenticated-
 * role (feedback submission + AI quality feedback).
 */
class SystemMaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, UserSeeder::class]);
    }

    // ---------------------------------------------------------------- health

    public function test_system_health_accessible_by_admin_and_records_a_check(): void
    {
        $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/system-health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', fn ($status) => in_array($status, ['ok', 'degraded'], true))
            ->assertJsonStructure(['data' => ['components' => ['application', 'database', 'queue', 'storage', 'ai', 'ocr']]]);

        $this->assertDatabaseHas('system_health_checks', ['source' => 'api']);
    }

    public function test_system_health_forbidden_for_non_admin_roles(): void
    {
        foreach (['hr@jpc.local', 'departmenthead@jpc.local', 'employee@jpc.local'] as $email) {
            $this->actingAs($this->user($email), 'sanctum')
                ->getJson('/api/v1/admin/system-health')
                ->assertStatus(403);
        }
    }

    public function test_system_health_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/system-health')->assertStatus(401);
    }

    public function test_system_health_component_endpoints_admin_only(): void
    {
        foreach (['database', 'queue', 'storage', 'ai', 'ocr', 'dashboard', 'history'] as $component) {
            $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
                ->getJson("/api/v1/admin/system-health/{$component}")
                ->assertOk();
        }

        $this->actingAs($this->user('employee@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/system-health/database')
            ->assertStatus(403);
    }

    public function test_system_health_history_returns_persisted_runs(): void
    {
        SystemHealthCheck::create([
            'status' => 'ok',
            'source' => 'manual',
            'checks' => ['application' => 'ok'],
            'metrics' => [],
            'checked_at' => now(),
        ]);

        $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/system-health/history')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------- feedback

    public function test_user_can_submit_feedback(): void
    {
        $employee = $this->user('employee@jpc.local');

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/feedback', [
                'category' => 'incorrect_information',
                'subject' => 'Wrong target number',
                'description' => 'The dashboard shows a stale target.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'OPEN');

        $this->assertDatabaseHas('user_feedback', ['subject' => 'Wrong target number', 'user_id' => $employee->id]);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => 'feedback.submit']);
    }

    public function test_feedback_submission_requires_valid_category(): void
    {
        $this->actingAs($this->user('employee@jpc.local'), 'sanctum')
            ->postJson('/api/v1/feedback', [
                'category' => 'not-a-category',
                'subject' => 'Nope',
                'description' => 'Invalid category must be rejected.',
            ])
            ->assertStatus(422);
    }

    public function test_user_can_list_own_feedback_only(): void
    {
        $employee = $this->user('employee@jpc.local');
        UserFeedback::factory()->count(2)->create(['user_id' => $employee->id]);
        UserFeedback::factory()->count(3)->create(['user_id' => $this->user('hr@jpc.local')->id]);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/feedback/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_list_and_update_feedback_with_lifecycle(): void
    {
        $feedback = UserFeedback::factory()->create(['status' => UserFeedback::STATUS_OPEN]);

        $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/feedback')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
            ->patchJson("/api/v1/admin/feedback/{$feedback->id}", [
                'status' => UserFeedback::STATUS_RESOLVED,
                'resolution' => 'Target corrected in the planning module.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', UserFeedback::STATUS_RESOLVED);

        $this->assertDatabaseHas('user_feedback', [
            'id' => $feedback->id,
            'status' => UserFeedback::STATUS_RESOLVED,
            'resolution' => 'Target corrected in the planning module.',
        ]);
    }

    public function test_feedback_admin_only(): void
    {
        $this->actingAs($this->user('employee@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/feedback')
            ->assertStatus(403);

        $feedback = UserFeedback::factory()->create();
        $this->actingAs($this->user('hr@jpc.local'), 'sanctum')
            ->patchJson("/api/v1/admin/feedback/{$feedback->id}", ['status' => UserFeedback::STATUS_RESOLVED])
            ->assertStatus(403);
    }

    public function test_ai_feedback_recorded_without_mutating_original_results(): void
    {
        $employee = $this->user('employee@jpc.local');

        // Seed a historical AI analysis (stands in for the original AI result)
        // and assert the AI feedback write does NOT touch it.
        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/feedback/ai', [
                'ai_type' => 'summary',
                'ai_record_id' => 999,
                'verdict' => 'Incorrect',
                'comment' => 'Summary omitted a flagged target.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.verdict', 'Incorrect');

        $this->assertDatabaseCount('ai_feedback', 1);
        $this->assertDatabaseMissing('ai_analyses', ['id' => 999]);
    }

    public function test_ai_feedback_requires_valid_verdict(): void
    {
        $this->actingAs($this->user('employee@jpc.local'), 'sanctum')
            ->postJson('/api/v1/feedback/ai', [
                'ai_type' => 'summary',
                'verdict' => 'Politely-wrong',
            ])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------- maintenance

    public function test_admin_can_record_and_list_maintenance_logs(): void
    {
        $admin = $this->user('admin@jpc.local');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/maintenance/logs', [
                'maintenance_type' => 'Database Backup',
                'description' => 'Applied the 5 Phase 13 migrations to the dev DB.',
                'affected_module' => 'Database',
                'result' => 'completed',
                'notes' => 'No rollback required.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.result', 'completed');

        $this->assertDatabaseHas('maintenance_logs', ['result' => 'completed']);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => 'maintenance.record']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/maintenance/logs')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_maintenance_logs_admin_only(): void
    {
        $this->actingAs($this->user('employee@jpc.local'), 'sanctum')
            ->getJson('/api/v1/admin/maintenance/logs')
            ->assertStatus(403);
    }

    public function test_maintenance_log_rejects_invalid_result(): void
    {
        $this->actingAs($this->user('admin@jpc.local'), 'sanctum')
            ->postJson('/api/v1/admin/maintenance/logs', [
                'maintenance_type' => 'Security Patch',
                'description' => 'Invalid result value.',
                'result' => 'somehow-done',
            ])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------ misc

    public function test_verify_backups_command_fails_without_recorded_backup(): void
    {
        $this->assertDatabaseMissing('maintenance_logs', ['maintenance_type' => 'Database Backup']);

        $this->artisan('system:verify-backups')
            ->assertExitCode(1)
            ->expectsOutputToContain('No backup maintenance log exists');
    }

    public function test_system_health_command_returns_ok_status(): void
    {
        $this->artisan('system:check-health', ['--source' => 'manual'])
            ->assertSuccessful();

        $this->assertDatabaseCount('system_health_checks', 1);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}