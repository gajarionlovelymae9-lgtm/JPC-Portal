<?php

namespace App\Enums;

/**
 * DataQualityIssueType — the single source of truth for all data-quality issue
 * categories detected by the Phase 8 validation engine (spec items 4, 53, 60,
 * 28, 65).
 *
 * The AI-assistant integration (Phase 7 / spec items 62-63) and the frontend
 * filter bar (spec item 38) MUST key their filters off these codes.
 */
enum DataQualityIssueType: string
{
    // ---- Employee issues (spec item 4) ----
    case MissingRequiredProfileData = 'MISSING_REQUIRED_PROFILE_DATA';
    case DuplicateEmployee = 'DUPLICATE_EMPLOYEE';
    case InvalidEmployeeStatus = 'INVALID_EMPLOYEE_STATUS';
    case InvalidDepartment = 'INVALID_DEPARTMENT';
    case InvalidPosition = 'INVALID_POSITION';
    case MissingEmployeeNumber = 'MISSING_EMPLOYEE_NUMBER';

    // ---- Department issues ----
    case InvalidDepartmentReference = 'INVALID_DEPARTMENT_REFERENCE';
    case DuplicateDepartment = 'DUPLICATE_DEPARTMENT';
    case InactiveDepartmentAssignment = 'INACTIVE_DEPARTMENT_ASSIGNMENT';

    // ---- Task issues ----
    case TaskWithoutAssignee = 'TASK_WITHOUT_ASSIGNEE';
    case TaskWithInvalidAssignee = 'TASK_WITH_INVALID_ASSIGNEE';
    case TaskWithoutDepartment = 'TASK_WITHOUT_DEPARTMENT';
    case InvalidTaskDates = 'INVALID_TASK_DATES';
    case DueDateBeforeStartDate = 'DUE_DATE_BEFORE_START_DATE';
    case OverdueTaskWithInconsistentStatus = 'OVERDUE_TASK_WITH_INCONSISTENT_STATUS';
    case DuplicateTask = 'DUPLICATE_TASK';

    // ---- Target issues ----
    case TargetWithoutEmployee = 'TARGET_WITHOUT_EMPLOYEE';
    case TargetWithoutPeriod = 'TARGET_WITHOUT_PERIOD';
    case InvalidTargetValue = 'INVALID_TARGET_VALUE';
    case DuplicateTarget = 'DUPLICATE_TARGET';
    case InconsistentTargetPeriod = 'INCONSISTENT_TARGET_PERIOD';

    // ---- Report issues ----
    case ReportWithoutTask = 'REPORT_WITHOUT_TASK';
    case ReportWithoutEmployee = 'REPORT_WITHOUT_EMPLOYEE';
    case ReportEmployeeMismatch = 'REPORT_EMPLOYEE_MISMATCH';
    case ReportTaskAssignmentMismatch = 'REPORT_TASK_ASSIGNMENT_MISMATCH';
    case InvalidReportDates = 'INVALID_REPORT_DATES';
    case ConflictingReportStatus = 'CONFLICTING_REPORT_STATUS';
    case DuplicateReport = 'DUPLICATE_REPORT';
    case MissingRequiredDocument = 'MISSING_REQUIRED_DOCUMENT';

    // ---- Performance issues ----
    case MissingPerformanceData = 'MISSING_PERFORMANCE_DATA';
    case DuplicatePerformanceSnapshot = 'DUPLICATE_PERFORMANCE_SNAPSHOT';
    case InvalidKpiValue = 'INVALID_KPI_VALUE';
    case InvalidProductivityScore = 'INVALID_PRODUCTIVITY_SCORE';
    case InvalidPerformancePeriod = 'INVALID_PERFORMANCE_PERIOD';
    case InconsistentScoreData = 'INCONSISTENT_SCORE_DATA';

    // ---- Relationship / orphan issues ----
    case OrphanedRecord = 'ORPHANED_RECORD';
    case BrokenReference = 'BROKEN_REFERENCE';
    case InvalidForeignRelationship = 'INVALID_FOREIGN_RELATIONSHIP';

    /**
     * Human-readable label used by API resources, dashboard cards and filters.
     */
    public function label(): string
    {
        $slug = str_replace('_', ' ', strtolower($this->value));
        return ucwords($slug);
    }

    /**
     * Broad category grouping for the dashboard "issues by category" chart
     * (spec item 36) and the admin report breakdown (spec item 65).
     */
    public function category(): string
    {
        return match ($this) {
            self::MissingRequiredProfileData,
            self::DuplicateEmployee,
            self::InvalidEmployeeStatus,
            self::InvalidDepartment,
            self::InvalidPosition,
            self::MissingEmployeeNumber => 'Employee',

            self::InvalidDepartmentReference,
            self::DuplicateDepartment,
            self::InactiveDepartmentAssignment => 'Department',

            self::TaskWithoutAssignee,
            self::TaskWithInvalidAssignee,
            self::TaskWithoutDepartment,
            self::InvalidTaskDates,
            self::DueDateBeforeStartDate,
            self::OverdueTaskWithInconsistentStatus,
            self::DuplicateTask => 'Task',

            self::TargetWithoutEmployee,
            self::TargetWithoutPeriod,
            self::InvalidTargetValue,
            self::DuplicateTarget,
            self::InconsistentTargetPeriod => 'Target',

            self::ReportWithoutTask,
            self::ReportWithoutEmployee,
            self::ReportEmployeeMismatch,
            self::ReportTaskAssignmentMismatch,
            self::InvalidReportDates,
            self::ConflictingReportStatus,
            self::DuplicateReport,
            self::MissingRequiredDocument => 'Report',

            self::MissingPerformanceData,
            self::DuplicatePerformanceSnapshot,
            self::InvalidKpiValue,
            self::InvalidProductivityScore,
            self::InvalidPerformancePeriod,
            self::InconsistentScoreData => 'Performance',

            self::OrphanedRecord,
            self::BrokenReference,
            self::InvalidForeignRelationship => 'Relationship',
        };
    }
}
