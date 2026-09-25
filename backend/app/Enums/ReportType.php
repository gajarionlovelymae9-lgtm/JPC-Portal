<?php

namespace App\Enums;

/**
 * ReportType — Phase 9 report catalog (spec items 3, 41, 43).
 *
 * Each report type maps to one runtime path so reports are auditable and the
 * AI Chat tools (spec items 58-60) can resolve "which report" without free-text.
 */
enum ReportType: string
{
    case EmployeeProgress = 'EMPLOYEE_PROGRESS';
    case EmployeePerformance = 'EMPLOYEE_PERFORMANCE';
    case EmployeeTask = 'EMPLOYEE_TASK';
    case EmployeeSubmission = 'EMPLOYEE_SUBMISSION';
    case EmployeeAchievement = 'EMPLOYEE_ACHIEVEMENT';
    case EmployeeProductivity = 'EMPLOYEE_PRODUCTIVITY';
    case DepartmentPerformance = 'DEPARTMENT_PERFORMANCE';
    case DepartmentTaskCompletion = 'DEPARTMENT_TASK_COMPLETION';
    case DepartmentReportSubmission = 'DEPARTMENT_REPORT_SUBMISSION';
    case DepartmentTargetAchievement = 'DEPARTMENT_TARGET_ACHIEVEMENT';
    case DepartmentAchievement = 'DEPARTMENT_ACHIEVEMENT';
    case InstitutionPerformance = 'INSTITUTION_PERFORMANCE';
    case InstitutionReportCompletion = 'INSTITUTION_REPORT_COMPLETION';
    case InstitutionTaskCompletion = 'INSTITUTION_TASK_COMPLETION';
    case InstitutionTarget = 'INSTITUTION_TARGET';
    case MonthlyPerformance = 'MONTHLY_PERFORMANCE';
    case MonthlyAchievement = 'MONTHLY_ACHIEVEMENT';
    case LateReports = 'LATE_REPORTS';
    case PendingReports = 'PENDING_REPORTS';
    case Targets = 'TARGETS';
    case ManagementSummary = 'MANAGEMENT_SUMMARY';
    case Achievement = 'ACHIEVEMENT';
    case DataQuality = 'DATA_QUALITY';

    public function label(): string
    {
        return match ($this) {
            self::EmployeeProgress => 'Employee Progress Report',
            self::EmployeePerformance => 'Employee Performance Report',
            self::EmployeeTask => 'Employee Task Report',
            self::EmployeeSubmission => 'Employee Submission Report',
            self::EmployeeAchievement => 'Employee Achievement Report',
            self::EmployeeProductivity => 'Employee Productivity Report',
            self::DepartmentPerformance => 'Department Performance Report',
            self::DepartmentTaskCompletion => 'Department Task Completion Report',
            self::DepartmentReportSubmission => 'Department Report Submission Report',
            self::DepartmentTargetAchievement => 'Department Target Achievement Report',
            self::DepartmentAchievement => 'Department Achievement Report',
            self::InstitutionPerformance => 'Institution Performance Report',
            self::InstitutionReportCompletion => 'Institution Report Completion Report',
            self::InstitutionTaskCompletion => 'Institution Task Completion Report',
            self::InstitutionTarget => 'Institution Target Report',
            self::MonthlyPerformance => 'Monthly Performance Report',
            self::MonthlyAchievement => 'Monthly Achievement Report',
            self::LateReports => 'Late Reports',
            self::PendingReports => 'Pending Reports',
            self::Targets => 'Targets Report',
            self::ManagementSummary => 'Management Summary',
            self::Achievement => 'Achievement Report',
            self::DataQuality => 'Data Quality Report',
        };
    }

    /**
     * Reporting domain used for authorization scoping (Admin/HR/Department Head).
     */
    public function domain(): string
    {
        return match ($this) {
            self::EmployeeProgress,
            self::EmployeePerformance,
            self::EmployeeTask,
            self::EmployeeSubmission,
            self::EmployeeAchievement,
            self::EmployeeProductivity => 'employee',
            self::DepartmentPerformance,
            self::DepartmentTaskCompletion,
            self::DepartmentReportSubmission,
            self::DepartmentTargetAchievement,
            self::DepartmentAchievement => 'department',
            default => 'institution',
        };
    }
}
