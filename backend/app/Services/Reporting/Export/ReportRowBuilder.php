<?php

namespace App\Services\Reporting\Export;

/**
 * ReportRowBuilder — Phase 9 export serializer (spec item 32).
 *
 * Flattens a generated report array into tabular ([columns, rows]) data so
 * the shared ReportExportService writers can stream or persist it. Keeping
 * the mapping here centralises column labels and value extraction for both
 * the live streaming exports and the queued pipeline.
 */
class ReportRowBuilder
{
    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    public function build(string $reportType, array $data): array
    {
        return match ($reportType) {
            'employee_progress' => $this->employee($data),
            'department_performance' => $this->department($data),
            'institution_performance' => $this->institution($data),
            'monthly_performance' => $this->monthly($data),
            'achievement_summary' => $this->achievements($data),
            'late_reports' => $this->late($data),
            'pending_reports' => $this->pending($data),
            'targets_summary' => $this->targets($data),
            'data_quality' => $this->dataQuality($data),
            default => [['columns' => [], 'rows' => []]],
        };
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function employee(array $data): array
    {
        $employee = $data['employee'] ?? [];
        $metrics = $data['metrics'] ?? [];

        return [
            'columns' => ['Employee', 'Employee No.', 'Department', 'Position', 'Tasks Assigned', 'Tasks Completed', 'Task Completion %', 'Reports Expected', 'Reports Submitted', 'Report Completion %', 'On-Time %', 'Approval %', 'Target Achievement %', 'Productivity', 'Rating'],
            'rows' => [[
                'Employee' => $employee['name'] ?? 'N/A',
                'Employee No.' => $employee['employee_number'] ?? '',
                'Department' => $employee['department'] ?? '',
                'Position' => $employee['position'] ?? '',
                'Tasks Assigned' => $metrics['tasks_assigned'] ?? 0,
                'Tasks Completed' => $metrics['tasks_completed'] ?? 0,
                'Task Completion %' => $this->pct($metrics['task_completion_rate']),
                'Reports Expected' => $metrics['reports_expected'] ?? 0,
                'Reports Submitted' => $metrics['reports_submitted'] ?? 0,
                'Report Completion %' => $this->pct($metrics['report_completion_rate']),
                'On-Time %' => $this->pct($metrics['on_time_submission_rate']),
                'Approval %' => $this->pct($metrics['approval_rate']),
                'Target Achievement %' => $this->pct($metrics['target_achievement_rate']),
                'Productivity' => $metrics['productivity_score'] !== null ? (float) $metrics['productivity_score'] : '',
                'Rating' => $metrics['rating'] ?? '',
            ]],
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function department(array $data): array
    {
        $department = $data['department'] ?? [];
        $metrics = $data['metrics'] ?? [];

        return [
            'columns' => ['Department', 'Employees', 'Tasks Assigned', 'Tasks Completed', 'Reports Submitted', 'Late Reports', 'Task Completion %', 'Report Completion %', 'On-Time %', 'Approval %', 'Target Achievement %', 'Average Productivity', 'Rating'],
            'rows' => [[
                'Department' => $department['name'] ?? 'N/A',
                'Employees' => $department['employee_count'] ?? 0,
                'Tasks Assigned' => $metrics['tasks_assigned'] ?? 0,
                'Tasks Completed' => $metrics['tasks_completed'] ?? 0,
                'Reports Submitted' => $metrics['reports_submitted'] ?? 0,
                'Late Reports' => $metrics['late_reports'] ?? 0,
                'Task Completion %' => $this->pct($metrics['task_completion_rate']),
                'Report Completion %' => $this->pct($metrics['report_completion_rate']),
                'On-Time %' => $this->pct($metrics['on_time_rate']),
                'Approval %' => $this->pct($metrics['approval_rate']),
                'Target Achievement %' => $this->pct($metrics['target_achievement_rate']),
                'Average Productivity' => $metrics['average_productivity'] !== null ? (float) $metrics['average_productivity'] : '',
                'Rating' => $metrics['rating'] ?? '',
            ]],
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function institution(array $data): array
    {
        $summary = $data['summary'] ?? [];

        return [
            'columns' => ['Metric', 'Value'],
            'rows' => [
                ['Metric' => 'Total Employees', 'Value' => $summary['total_employees'] ?? 0],
                ['Metric' => 'Total Departments', 'Value' => $summary['total_departments'] ?? 0],
                ['Metric' => 'Tasks Assigned', 'Value' => $summary['tasks_assigned'] ?? 0],
                ['Metric' => 'Tasks Completed', 'Value' => $summary['tasks_completed'] ?? 0],
                ['Metric' => 'Task Completion %', 'Value' => $this->pct($summary['task_completion_rate'])],
                ['Metric' => 'Reports Submitted', 'Value' => $summary['reports_submitted'] ?? 0],
                ['Metric' => 'Late Reports', 'Value' => $summary['late_reports'] ?? 0],
                ['Metric' => 'Pending Reports', 'Value' => $summary['pending_reports'] ?? 0],
                ['Metric' => 'Approval %', 'Value' => $this->pct($summary['approval_rate'])],
                ['Metric' => 'Target Achievement %', 'Value' => $this->pct($summary['target_achievement_rate'])],
                ['Metric' => 'Average Productivity', 'Value' => $summary['average_productivity'] !== null ? (float) $summary['average_productivity'] : ''],
                ['Metric' => 'Data Quality Score', 'Value' => $this->pct($summary['data_quality_score'])],
            ],
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function monthly(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $rankings = $data['department_rankings'] ?? [];

        $rows = [
            [
                'Period' => $data['period'] ?? '',
                'Scope' => $data['scope'] ?? '',
                'Employees Evaluated' => $summary['employees_evaluated'] ?? 0,
                'Average Productivity' => $summary['average_productivity'] !== null ? (float) $summary['average_productivity'] : '',
                'Performance Change' => $summary['performance_change'] !== null ? (float) $summary['performance_change'] : '',
                'Top Department' => ($data['top_department']['department_name'] ?? ''),
                'Late Reports' => $summary['late_reports'] ?? 0,
            ],
        ];

        $rows = array_merge($rows, array_map(fn ($rank) => [
            'Period' => $data['period'] ?? '',
            'Scope' => 'department_ranking',
            'Employees Evaluated' => $rank['employees_evaluated'] ?? 0,
            'Average Productivity' => $rank['average_score'] !== null ? (float) $rank['average_score'] : '',
            'Performance Change' => '',
            'Top Department' => $rank['department_name'] ?? '',
            'Late Reports' => '',
        ], $rankings));

        return [
            'columns' => ['Period', 'Scope', 'Employees Evaluated', 'Average Productivity', 'Performance Change', 'Top Department', 'Late Reports'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function achievements(array $data): array
    {
        $rows = array_map(fn ($row) => [
            'Employee' => $row['employee_name'] ?? 'N/A',
            'Department' => $row['department'] ?? '',
            'Period' => $row['period'] ?? '',
            'Performance Score' => $row['performance_score'] !== null ? (float) $row['performance_score'] : '',
            'Completion %' => $this->pct($row['completion_rate']),
            'On-Time %' => $this->pct($row['on_time_rate']),
            'Completed Tasks' => $row['completed_tasks'] ?? 0,
            'Approved Reports' => $row['approved_reports'] ?? 0,
            'Rank' => $row['rank'] ?? '',
            'Employee of Month' => $row['is_employee_of_month'] ? 'Yes' : 'No',
            'Badge' => $row['badge']['name'] ?? '',
        ], $data['achievements'] ?? []);

        return [
            'columns' => ['Employee', 'Department', 'Period', 'Performance Score', 'Completion %', 'On-Time %', 'Completed Tasks', 'Approved Reports', 'Rank', 'Employee of Month', 'Badge'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function late(array $data): array
    {
        $rows = array_map(fn ($row) => [
            'Title' => $row['title'] ?? '',
            'Employee' => $row['employee'] ?? 'N/A',
            'Department' => $row['department'] ?? '',
            'Submitted At' => $row['submitted_at'] ?? '',
            'Deadline' => $row['deadline'] ?? '',
            'Status' => $row['status'] ?? '',
        ], $data['reports'] ?? []);

        return [
            'columns' => ['Title', 'Employee', 'Department', 'Submitted At', 'Deadline', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function pending(array $data): array
    {
        $rows = array_map(fn ($row) => [
            'Title' => $row['title'] ?? '',
            'Employee' => $row['employee'] ?? 'N/A',
            'Department' => $row['department'] ?? '',
            'Status' => $row['status'] ?? '',
            'Submitted At' => $row['submitted_at'] ?? '',
        ], $data['reports'] ?? []);

        return [
            'columns' => ['Title', 'Employee', 'Department', 'Status', 'Submitted At'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function targets(array $data): array
    {
        $rows = array_map(fn ($row) => [
            'Title' => $row['title'] ?? '',
            'Employee' => $row['employee'] ?? 'N/A',
            'Department' => $row['department'] ?? '',
            'Status' => $row['status'] ?? '',
            'Target Value' => (float) ($row['target_value'] ?? 0),
            'Unit' => $row['target_unit'] ?? '',
            'Start Date' => $row['start_date'] ?? '',
            'End Date' => $row['end_date'] ?? '',
        ], $data['targets'] ?? []);

        return [
            'columns' => ['Title', 'Employee', 'Department', 'Status', 'Target Value', 'Unit', 'Start Date', 'End Date'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function dataQuality(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $rows = [
            [
                'Department' => '— all —',
                'Issues' => $summary['total_issues'] ?? 0,
                'Critical' => $summary['critical'] ?? 0,
                'High' => $summary['high'] ?? 0,
                'Medium' => $summary['medium'] ?? 0,
                'Low' => $summary['low'] ?? 0,
                'Score' => $this->pct($summary['data_quality_score']),
            ],
        ];

        foreach (($data['by_department'] ?? []) as $row) {
            $rows[] = [
                'Department' => $row['department_name'] ?? $row['name'] ?? '',
                'Issues' => $row['issue_count'] ?? $row['total'] ?? 0,
                'Critical' => $row['critical'] ?? $row['breakdown']['critical'] ?? 0,
                'High' => $row['high'] ?? $row['breakdown']['high'] ?? 0,
                'Medium' => $row['medium'] ?? $row['breakdown']['medium'] ?? 0,
                'Low' => $row['low'] ?? $row['breakdown']['low'] ?? 0,
                'Score' => '',
            ];
        }

        return [
            'columns' => ['Department', 'Issues', 'Critical', 'High', 'Medium', 'Low', 'Score'],
            'rows' => $rows,
        ];
    }

    private function pct(mixed $value): string
    {
        return $value === null ? '' : number_format((float) $value, 1).'%';
    }
}