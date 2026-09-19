<?php

namespace Tests\Feature\Api\Reports;

use App\Models\Department;
use Illuminate\Support\Carbon;

class ReportExportTest extends ReportTestCase
{
    public function test_csv_export_streams_headers_and_rows(): void
    {
        $dept = Department::factory()->create();
        $this->login($this->adminUser());

        $this->taskIn($dept, ['status' => 'completed', 'due_date' => now()->addDays(5), 'completed_at' => now()->subDay()]);
        $this->taskIn($dept, ['status' => 'in_progress']);

        $response = $this->get('/api/v1/reports/export/task-completion?format=csv')->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="JPC_Task_Completion_Report_', $response->headers->get('content-disposition'));

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Metric,Value', $body);
        $this->assertStringContainsString('Total tasks', $body);
        $this->assertStringContainsString('Completion rate (%)', $body);
    }

    public function test_xlsx_exports_as_valid_office_open_xml_package(): void
    {
        $dept = Department::factory()->create();
        $emp = $this->employee($dept);
        $this->completedAssignment($emp, $dept, Carbon::parse('-2 days'), Carbon::parse('+5 days'));
        $this->login($this->adminUser());

        $body = $this->get('/api/v1/reports/export/employee-performance?format=xlsx')
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith('PK', $body);

        $tmp = tempnam(sys_get_temp_dir(), 'jpc_xlsx_test_');
        file_put_contents($tmp, $body);
        $zip = new \ZipArchive;
        try {
            $this->assertTrue($zip->open($tmp) === true, 'XLSX must open as a valid ZIP archive');
            $this->assertStringContainsString('<sheet name="Report"', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('employee_name', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
        } finally {
            @unlink($tmp);
        }
    }

    public function test_pdf_export_is_a_valid_pdf_stream(): void
    {
        $dept = Department::factory()->create();
        $this->taskIn($dept, ['status' => 'in_progress']);
        $this->login($this->adminUser());

        $header = $this->get('/api/v1/reports/export/task-completion?format=pdf')
            ->assertOk()
            ->headers
            ->get('content-type');

        $body = $this->get('/api/v1/reports/export/task-completion?format=pdf')->streamedContent();

        $this->assertStringContainsString('application/pdf', $header);
        $this->assertStringStartsWith('%PDF-1.4', $body);
    }

    public function test_export_is_audited_in_activity_log(): void
    {
        $dept = Department::factory()->create();
        $admin = $this->adminUser();
        $this->taskIn($dept, ['status' => 'in_progress']);
        $this->login($admin);

        $this->get('/api/v1/reports/export/task-completion?format=csv')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'report.export',
            'user_id' => $admin->id,
        ]);

        $log = \App\Models\ActivityLog::query()
            ->where('activity_type', 'report.export')
            ->firstOrFail();
        $this->assertEquals('task-completion', $log->metadata['report_type']);
        $this->assertEquals('csv', $log->metadata['format']);
    }

    public function test_department_head_export_is_scoped_to_own_department(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $head = $this->departmentHeadUser($deptA);
        $empA = $this->employee($deptA);
        $empB = $this->employee($deptB);

        $this->completedAssignment($empA, $deptA, Carbon::parse('-2 days'), Carbon::parse('+5 days'));
        $this->completedAssignment($empB, $deptB, Carbon::parse('-2 days'), Carbon::parse('+5 days'));

        $this->login($head);

        $body = $this->get('/api/v1/reports/export/employee-performance?format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($empA->fullName, $body);
        $this->assertStringNotContainsString($empB->fullName, $body);
    }

    public function test_export_formula_injection_is_neutralized(): void
    {
        $dept = Department::factory()->create();
        $emp = $this->employee($dept);
        $emp->update(['first_name' => '=SUM(A1)', 'middle_name' => null]);
        $this->completedAssignment($emp, $dept, Carbon::parse('-2 days'), Carbon::parse('+5 days'));
        $this->login($this->adminUser());

        $body = $this->get('/api/v1/reports/export/employee-performance?format=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=SUM(A1)", $body);
    }

    public function test_invalid_export_format_is_rejected(): void
    {
        $this->login($this->adminUser());

        $this->get('/api/v1/reports/export/task-completion?format=exe')->assertUnprocessable();
    }

    public function test_employee_export_route_is_permission_guarded(): void
    {
        $dept = Department::factory()->create();
        $this->login($this->employeeUser($dept));

        $this->get('/api/v1/reports/export/task-completion')->assertForbidden();
    }

    public function test_export_rate_limiter_blocks_bursts(): void
    {
        $dept = Department::factory()->create();
        $admin = $this->adminUser();
        $this->taskIn($dept, ['status' => 'in_progress']);
        $this->login($admin);

        for ($i = 0; $i < 10; $i++) {
            $this->get('/api/v1/reports/export/task-completion?format=csv')->assertOk();
        }

        $this->get('/api/v1/reports/export/task-completion?format=csv')->assertStatus(429);
    }
}