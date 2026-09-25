<?php

namespace Tests\Feature\Reporting;

use App\Enums\ReportExportStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ReportExport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 9 — ReportExport pipeline HTTP tests (spec items 32-33, 53-55):
 * queueing, status polling, ownership, expiry and file streaming.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function employeeUser(): array
    {
        $department = Department::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        return [$user, $employee];
    }

    public function test_admin_can_queue_institution_export(): void
    {
        $user = $this->adminUser();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/reports/exports', [
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'year' => now()->year,
            'month' => now()->month,
        ])->assertStatus(202);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.id'));

        $this->assertDatabaseHas('report_exports', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
        ]);
    }

    public function test_excel_wire_format_is_normalized_to_xlsx(): void
    {
        $user = $this->adminUser();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/exports', [
            'report_type' => 'late_reports',
            'format' => 'excel',
        ])->assertStatus(202)
            ->assertJsonPath('data.format', 'xlsx');
    }

    public function test_invalid_export_format_is_rejected(): void
    {
        $user = $this->adminUser();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/exports', [
            'report_type' => 'institution_performance',
            'format' => 'docx',
        ])->assertStatus(422);
    }

    public function test_owner_can_poll_export_and_download_completed_file(): void
    {
        $user = $this->adminUser();

        Sanctum::actingAs($user);

        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Completed->value,
            'filters' => ['year' => now()->year, 'month' => now()->month],
            'file_path' => 'exports/test.csv',
            'file_name' => 'test.csv',
            'file_size' => 1,
        ]);

        Storage::disk(ReportExport::DISK)->put($export->file_path, "Metric,Value\nemployees,3\n");

        $this->getJson('/api/v1/reports/exports/'.$export->id)
            ->assertOk()
            ->assertJsonPath('data.status', ReportExportStatus::Completed->value);

        $download = $this->get('/api/v1/reports/exports/'.$export->id.'/download');

        $download->assertOk();
        $download->assertHeader('content-disposition');
        $this->assertSame(
            "Metric,Value\nemployees,3\n",
            Storage::disk(ReportExport::DISK)->get($export->file_path),
        );
    }

    public function test_export_is_ownership_scoped(): void
    {
        [$owner] = $this->employeeUser();
        [$intruder] = $this->employeeUser();
        $admin = $this->adminUser();

        $export = ReportExport::create([
            'user_id' => $owner->id,
            'report_type' => 'employee_progress',
            'format' => 'csv',
            'status' => ReportExportStatus::Completed->value,
            'filters' => [],
        ]);

        Sanctum::actingAs($intruder);
        $this->getJson('/api/v1/reports/exports/'.$export->id)->assertStatus(403);
        $this->getJson('/api/v1/reports/exports/'.$export->id.'/download')->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/reports/exports/'.$export->id)->assertOk();
    }

    public function test_export_index_lists_only_own_exports_for_employee(): void
    {
        [$owner] = $this->employeeUser();
        [$otherUser] = $this->employeeUser();

        ReportExport::create([
            'user_id' => $owner->id,
            'report_type' => 'employee_progress',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => [],
        ]);
        ReportExport::create([
            'user_id' => $otherUser->id,
            'report_type' => 'employee_progress',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => [],
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/exports')->assertOk();
        $items = collect($response->json('data'));
        $this->assertSame(1, $items->count());
        $this->assertSame('employee_progress', $items->first()['report_type']);
    }

    public function test_download_of_queued_export_returns_409(): void
    {
        $user = $this->adminUser();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Queued->value,
            'filters' => [],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/exports/'.$export->id.'/download')
            ->assertStatus(409);
    }

    public function test_download_of_expired_export_returns_410(): void
    {
        $user = $this->adminUser();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'report_type' => 'institution_performance',
            'format' => 'csv',
            'status' => ReportExportStatus::Completed->value,
            'filters' => [],
            'file_path' => 'exports/stale.csv',
            'file_name' => 'stale.csv',
            'expires_at' => now()->subHour(),
        ]);

        Storage::disk(ReportExport::DISK)->put($export->file_path, 'a,b');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/exports/'.$export->id.'/download')
            ->assertStatus(410);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'status' => ReportExportStatus::Expired->value,
        ]);
    }

    public function test_missing_export_returns_404(): void
    {
        $user = $this->adminUser();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/exports/99999')->assertStatus(404);
    }
}