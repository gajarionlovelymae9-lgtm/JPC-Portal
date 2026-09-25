<?php

namespace App\Enums;

/**
 * Centralized permission catalog and default role assignments.
 *
 * Permission names use the "<module>.<action>" convention so future modules
 * can reuse them without touching the authorization architecture.
 */
final class Permissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const HR_DASHBOARD_VIEW = 'hr.dashboard.view';

    public const USERS_VIEW = 'users.view';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_DELETE = 'users.delete';

    public const ROLES_VIEW = 'roles.view';

    public const ROLES_CREATE = 'roles.create';

    public const ROLES_UPDATE = 'roles.update';

    public const ROLES_DELETE = 'roles.delete';

    public const PERMISSIONS_VIEW = 'permissions.view';

    public const PERMISSIONS_MANAGE = 'permissions.manage';

    public const EMPLOYEES_VIEW = 'employees.view';

    public const EMPLOYEES_CREATE = 'employees.create';

    public const EMPLOYEES_UPDATE = 'employees.update';

    public const EMPLOYEES_DELETE = 'employees.delete';

    public const DEPARTMENTS_VIEW = 'departments.view';

    public const DEPARTMENTS_CREATE = 'departments.create';

    public const DEPARTMENTS_UPDATE = 'departments.update';

    public const DEPARTMENTS_DELETE = 'departments.delete';

    public const POSITIONS_VIEW = 'positions.view';

    public const POSITIONS_CREATE = 'positions.create';

    public const POSITIONS_UPDATE = 'positions.update';

    public const POSITIONS_DELETE = 'positions.delete';

    public const ACADEMIC_YEARS_VIEW = 'academic_years.view';

    public const ACADEMIC_YEARS_CREATE = 'academic_years.create';

    public const ACADEMIC_YEARS_UPDATE = 'academic_years.update';

    public const ACADEMIC_YEARS_DELETE = 'academic_years.delete';

    public const ANNUAL_PLANS_VIEW = 'annual_plans.view';

    public const ANNUAL_PLANS_CREATE = 'annual_plans.create';

    public const ANNUAL_PLANS_UPDATE = 'annual_plans.update';

    public const ANNUAL_PLANS_APPROVE = 'annual_plans.approve';

    public const PLANNING_VIEW = 'planning.view';

    public const PLANNING_CREATE = 'planning.create';

    public const PLANNING_UPDATE = 'planning.update';

    public const PLANNING_DELETE = 'planning.delete';

    public const TARGETS_VIEW = 'targets.view';

    public const TARGETS_CREATE = 'targets.create';

    public const TARGETS_UPDATE = 'targets.update';

    public const TARGETS_DELETE = 'targets.delete';

    public const TASKS_VIEW = 'tasks.view';

    public const TASKS_CREATE = 'tasks.create';

    public const TASKS_UPDATE = 'tasks.update';

    public const TASKS_DELETE = 'tasks.delete';

    public const TASKS_ASSIGN = 'tasks.assign';

    public const TASKS_COMPLETE = 'tasks.complete';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_CREATE = 'reports.create';

    public const REPORTS_UPDATE = 'reports.update';

    public const REPORTS_DELETE = 'reports.delete';

    public const REPORTS_SUBMIT = 'reports.submit';

    public const REPORTS_REVIEW = 'reports.review';

    public const REPORTS_APPROVE = 'reports.approve';

    public const REPORTS_REJECT = 'reports.reject';

    public const REPORT_FILES_UPLOAD = 'report_files.upload';

    public const REPORT_FILES_VIEW = 'report_files.view';

    public const REPORT_FILES_DELETE = 'report_files.delete';

    public const OCR_VIEW = 'ocr.view';

    public const OCR_PROCESS = 'ocr.process';

    public const AI_ANALYSIS_VIEW = 'ai.analysis.view';

    public const AI_ANALYSIS_RUN = 'ai.analysis.run';

    public const AI_SUMMARY_VIEW = 'ai.summary.view';

    public const AI_VALIDATION_VIEW = 'ai.validation.view';

    public const AI_VALIDATION_RUN = 'ai.validation.run';

    public const AI_SIMILARITY_VIEW = 'ai.similarity.view';

    public const AI_SIMILARITY_RUN = 'ai.similarity.run';

    public const PERFORMANCE_VIEW = 'performance.view';

    public const PERFORMANCE_MANAGE = 'performance.manage';

    public const ACHIEVEMENTS_VIEW = 'achievements.view';

    public const ACHIEVEMENTS_MANAGE = 'achievements.manage';

    public const BADGES_VIEW = 'badges.view';

    public const BADGES_CREATE = 'badges.create';

    public const BADGES_UPDATE = 'badges.update';

    public const BADGES_DELETE = 'badges.delete';

    public const BADGES_AWARD = 'badges.award';

    public const NOTIFICATIONS_VIEW = 'notifications.view';

    public const NOTIFICATIONS_MANAGE = 'notifications.manage';

    public const AUDIT_LOGS_VIEW = 'audit_logs.view';

    public const ACTIVITY_LOGS_VIEW = 'activity_logs.view';

    public const ANALYTICS_VIEW = 'analytics.view';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    /**
     * Every permission in the system, keyed by its "module.group".
     *
     * @return array<string, array<string, string>> group => [permission name => label]
     */
    public static function catalog(): array
    {
        return [
            'dashboard' => [self::DASHBOARD_VIEW => 'View Dashboard'],
            'hr' => [self::HR_DASHBOARD_VIEW => 'View HR Dashboard'],
            'users' => [
                self::USERS_VIEW => 'View Users',
                self::USERS_CREATE => 'Create Users',
                self::USERS_UPDATE => 'Update Users',
                self::USERS_DELETE => 'Delete Users',
            ],
            'roles' => [
                self::ROLES_VIEW => 'View Roles',
                self::ROLES_CREATE => 'Create Roles',
                self::ROLES_UPDATE => 'Update Roles',
                self::ROLES_DELETE => 'Delete Roles',
            ],
            'permissions' => [
                self::PERMISSIONS_VIEW => 'View Permissions',
                self::PERMISSIONS_MANAGE => 'Manage Permissions',
            ],
            'employees' => [
                self::EMPLOYEES_VIEW => 'View Employees',
                self::EMPLOYEES_CREATE => 'Create Employees',
                self::EMPLOYEES_UPDATE => 'Update Employees',
                self::EMPLOYEES_DELETE => 'Delete Employees',
            ],
            'departments' => [
                self::DEPARTMENTS_VIEW => 'View Departments',
                self::DEPARTMENTS_CREATE => 'Create Departments',
                self::DEPARTMENTS_UPDATE => 'Update Departments',
                self::DEPARTMENTS_DELETE => 'Delete Departments',
            ],
            'positions' => [
                self::POSITIONS_VIEW => 'View Positions',
                self::POSITIONS_CREATE => 'Create Positions',
                self::POSITIONS_UPDATE => 'Update Positions',
                self::POSITIONS_DELETE => 'Delete Positions',
            ],
            'academic_years' => [
                self::ACADEMIC_YEARS_VIEW => 'View Academic Years',
                self::ACADEMIC_YEARS_CREATE => 'Create Academic Years',
                self::ACADEMIC_YEARS_UPDATE => 'Update Academic Years',
                self::ACADEMIC_YEARS_DELETE => 'Delete Academic Years',
            ],
            'annual_plans' => [
                self::ANNUAL_PLANS_VIEW => 'View Annual Plans',
                self::ANNUAL_PLANS_CREATE => 'Create Annual Plans',
                self::ANNUAL_PLANS_UPDATE => 'Update Annual Plans',
                self::ANNUAL_PLANS_APPROVE => 'Approve Annual Plans',
            ],
            'planning' => [
                self::PLANNING_VIEW => 'View Planning (legacy)',
                self::PLANNING_CREATE => 'Create Planning (legacy)',
                self::PLANNING_UPDATE => 'Update Planning (legacy)',
                self::PLANNING_DELETE => 'Delete Planning (legacy)',
            ],
            'targets' => [
                self::TARGETS_VIEW => 'View Targets',
                self::TARGETS_CREATE => 'Create Targets',
                self::TARGETS_UPDATE => 'Update Targets',
                self::TARGETS_DELETE => 'Delete Targets',
            ],
            'tasks' => [
                self::TASKS_VIEW => 'View Tasks',
                self::TASKS_CREATE => 'Create Tasks',
                self::TASKS_UPDATE => 'Update Tasks',
                self::TASKS_DELETE => 'Delete Tasks',
                self::TASKS_ASSIGN => 'Assign Tasks',
                self::TASKS_COMPLETE => 'Complete Tasks',
            ],
            'reports' => [
                self::REPORTS_VIEW => 'View Reports',
                self::REPORTS_CREATE => 'Create Reports',
                self::REPORTS_UPDATE => 'Update Reports',
                self::REPORTS_DELETE => 'Delete Reports',
                self::REPORTS_SUBMIT => 'Submit Reports',
                self::REPORTS_REVIEW => 'Review Reports',
                self::REPORTS_APPROVE => 'Approve Reports',
                self::REPORTS_REJECT => 'Reject Reports',
            ],
            'report_files' => [
                self::REPORT_FILES_UPLOAD => 'Upload Report Files',
                self::REPORT_FILES_VIEW => 'View Report Files',
                self::REPORT_FILES_DELETE => 'Delete Report Files',
            ],
            'ocr' => [
                self::OCR_VIEW => 'View OCR Results',
                self::OCR_PROCESS => 'Run OCR',
            ],
            'ai.analysis' => [
                self::AI_ANALYSIS_VIEW => 'View AI Analysis',
                self::AI_ANALYSIS_RUN => 'Run AI Analysis',
            ],
            'ai.summary' => [
                self::AI_SUMMARY_VIEW => 'View AI Summary',
            ],
            'ai.validation' => [
                self::AI_VALIDATION_VIEW => 'View AI Validation',
                self::AI_VALIDATION_RUN => 'Run AI Validation',
            ],
            'ai.similarity' => [
                self::AI_SIMILARITY_VIEW => 'View AI Similarity',
                self::AI_SIMILARITY_RUN => 'Run AI Similarity',
            ],
            'performance' => [
                self::PERFORMANCE_VIEW => 'View Performance',
                self::PERFORMANCE_MANAGE => 'Manage Performance',
            ],
            'achievements' => [
                self::ACHIEVEMENTS_VIEW => 'View Achievements',
                self::ACHIEVEMENTS_MANAGE => 'Manage Achievements',
            ],
            'badges' => [
                self::BADGES_VIEW => 'View Badges',
                self::BADGES_CREATE => 'Create Badges',
                self::BADGES_UPDATE => 'Update Badges',
                self::BADGES_DELETE => 'Delete Badges',
                self::BADGES_AWARD => 'Award Badges',
            ],
            'notifications' => [
                self::NOTIFICATIONS_VIEW => 'View Notifications',
                self::NOTIFICATIONS_MANAGE => 'Manage Notifications',
            ],
            'audit_logs' => [self::AUDIT_LOGS_VIEW => 'View Audit Logs'],
            'activity_logs' => [self::ACTIVITY_LOGS_VIEW => 'View Activity Logs'],
            'analytics' => [self::ANALYTICS_VIEW => 'View Analytics'],
            'settings' => [
                self::SETTINGS_VIEW => 'View Settings',
                self::SETTINGS_MANAGE => 'Manage Settings',
            ],
        ];
    }

    /**
     * Flattened list of every permission name.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::catalog())));
    }

    /**
     * Permissions granted to the Administrator role (full system access).
     *
     * @return list<string>
     */
    public static function forAdmin(): array
    {
        return self::all();
    }

    /**
     * Permissions granted to the HR role (employee-centric, no system admin).
     *
     * @return list<string>
     */
    public static function forHr(): array
    {
        return [
            self::DASHBOARD_VIEW,
            self::HR_DASHBOARD_VIEW,
            self::USERS_VIEW,
            self::EMPLOYEES_VIEW,
            self::EMPLOYEES_CREATE,
            self::EMPLOYEES_UPDATE,
            self::DEPARTMENTS_VIEW,
            self::POSITIONS_VIEW,
            self::ACADEMIC_YEARS_VIEW,
            self::ANNUAL_PLANS_VIEW,
            self::TARGETS_VIEW,
            self::TASKS_VIEW,
            self::REPORTS_VIEW,
            self::REPORT_FILES_VIEW,
            self::OCR_VIEW,
            self::AI_ANALYSIS_VIEW,
            self::AI_SUMMARY_VIEW,
            self::AI_VALIDATION_VIEW,
            self::AI_SIMILARITY_VIEW,
            self::PERFORMANCE_VIEW,
            self::ACHIEVEMENTS_VIEW,
            self::BADGES_VIEW,
            self::NOTIFICATIONS_VIEW,
            self::NOTIFICATIONS_MANAGE,
            self::ACTIVITY_LOGS_VIEW,
            self::ANALYTICS_VIEW,
        ];
    }

    /**
     * Permissions granted to the Department Head role (own department scope).
     *
     * @return list<string>
     */
    public static function forDepartmentHead(): array
    {
        return [
            self::DASHBOARD_VIEW,
            self::EMPLOYEES_VIEW,
            self::DEPARTMENTS_VIEW,
            self::POSITIONS_VIEW,
            self::ANNUAL_PLANS_VIEW,
            self::ANNUAL_PLANS_CREATE,
            self::ANNUAL_PLANS_UPDATE,
            self::PLANNING_VIEW,
            self::TARGETS_VIEW,
            self::TARGETS_CREATE,
            self::TARGETS_UPDATE,
            self::TASKS_VIEW,
            self::TASKS_CREATE,
            self::TASKS_UPDATE,
            self::TASKS_DELETE,
            self::TASKS_ASSIGN,
            self::TASKS_COMPLETE,
            self::REPORTS_VIEW,
            self::REPORTS_REVIEW,
            self::REPORTS_APPROVE,
            self::REPORTS_REJECT,
            self::REPORT_FILES_VIEW,
            self::OCR_VIEW,
            self::AI_ANALYSIS_VIEW,
            self::AI_ANALYSIS_RUN,
            self::AI_SUMMARY_VIEW,
            self::AI_VALIDATION_VIEW,
            self::AI_VALIDATION_RUN,
            self::AI_SIMILARITY_VIEW,
            self::PERFORMANCE_VIEW,
            self::ACHIEVEMENTS_VIEW,
            self::BADGES_VIEW,
            self::BADGES_AWARD,
            self::NOTIFICATIONS_VIEW,
            self::NOTIFICATIONS_MANAGE,
            self::ACTIVITY_LOGS_VIEW,
            self::ANALYTICS_VIEW,
        ];
    }

    /**
     * Permissions granted to the Employee role (own records only).
     *
     * @return list<string>
     */
    public static function forEmployee(): array
    {
        return [
            self::DASHBOARD_VIEW,
            self::EMPLOYEES_VIEW,
            self::DEPARTMENTS_VIEW,
            self::TARGETS_VIEW,
            self::TASKS_VIEW,
            self::TASKS_COMPLETE,
            self::REPORTS_VIEW,
            self::REPORTS_CREATE,
            self::REPORTS_UPDATE,
            self::REPORTS_SUBMIT,
            self::REPORT_FILES_UPLOAD,
            self::REPORT_FILES_VIEW,
            self::REPORT_FILES_DELETE,
            self::OCR_VIEW,
            self::AI_ANALYSIS_VIEW,
            self::AI_SUMMARY_VIEW,
            self::AI_VALIDATION_VIEW,
            self::AI_SIMILARITY_VIEW,
            self::PERFORMANCE_VIEW,
            self::ACHIEVEMENTS_VIEW,
            self::BADGES_VIEW,
            self::NOTIFICATIONS_VIEW,
        ];
    }

    /**
     * Default permission names assigned to every role.
     *
     * @return array<string, list<string>>
     */
    public static function assignments(): array
    {
        return [
            Role::Admin->value => self::forAdmin(),
            Role::Hr->value => self::forHr(),
            Role::DepartmentHead->value => self::forDepartmentHead(),
            Role::Employee->value => self::forEmployee(),
        ];
    }
}
