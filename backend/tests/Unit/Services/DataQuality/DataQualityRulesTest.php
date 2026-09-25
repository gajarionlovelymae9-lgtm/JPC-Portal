<?php

namespace Tests\Unit\Services\DataQuality;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueType;
use App\Models\Employee;
use App\Models\EmployeePerformanceRecord;
use App\Models\Report;
use App\Models\Task;
use App\Services\DataQuality\Rules\EmployeeRequiredFieldsRule;
use App\Services\DataQuality\Rules\PerformanceConsistencyRule;
use App\Services\DataQuality\Rules\ReportDateRule;
use App\Services\DataQuality\Rules\TaskDateRule;
use Carbon\Carbon;
use Tests\TestCase;

class DataQualityRulesTest extends TestCase
{
    public function test_employee_required_fields_rule_flags_missing_data(): void
    {
        $rule = new EmployeeRequiredFieldsRule();

        $employee = new Employee([
            'first_name' => '',
            'last_name' => 'Doe',
            'employee_number' => 'EMP-001',
            'department_id' => 1,
            'position_id' => 1,
            'employment_status' => 'active',
        ]);
        $employee->id = 99;

        $result = $rule->check($employee);

        $this->assertNotNull($result);
        $this->assertEquals(DataQualityIssueType::MISSING_REQUIRED_PROFILE_DATA, $result['issue_type']);
        $this->assertEquals(DataQualityIssueSeverity::HIGH, $result['severity']);
        $this->assertContains('first_name', $result['detected_value']['missing_fields']);
    }

    public function test_task_date_rule_flags_due_before_start(): void
    {
        $rule = new TaskDateRule();

        $task = new Task([
            'title' => 'Sample Inconsistent Task',
            'start_date' => '2026-09-15',
            'due_date' => '2026-09-10',
            'department_id' => 1,
        ]);
        $task->id = 101;

        $result = $rule->check($task);

        $this->assertNotNull($result);
        $this->assertEquals(DataQualityIssueType::DUE_DATE_BEFORE_START_DATE, $result['issue_type']);
        $this->assertEquals(DataQualityIssueSeverity::CRITICAL, $result['severity']);
    }

    public function test_report_date_rule_flags_approval_preceding_submission(): void
    {
        $rule = new ReportDateRule();

        $report = new Report([
            'title' => 'Anomalous Report',
            'submitted_at' => Carbon::parse('2026-09-20 14:00:00'),
            'approved_at' => Carbon::parse('2026-09-20 10:00:00'),
            'employee_id' => 1,
            'department_id' => 1,
        ]);
        $report->id = 202;

        $result = $rule->check($report);

        $this->assertNotNull($result);
        $this->assertEquals(DataQualityIssueType::INVALID_REPORT_DATES, $result['issue_type']);
    }

    public function test_performance_consistency_rule_flags_out_of_bounds_kpi(): void
    {
        $rule = new PerformanceConsistencyRule();

        $record = new EmployeePerformanceRecord([
            'employee_id' => 1,
            'completion_rate' => 105.00,
            'overall_score' => 95.00,
        ]);
        $record->id = 303;

        $result = $rule->check($record);

        $this->assertNotNull($result);
        $this->assertEquals(DataQualityIssueType::INVALID_KPI_VALUE, $result['issue_type']);
        $this->assertArrayHasKey('completion_rate', $result['detected_value']);
    }
}
