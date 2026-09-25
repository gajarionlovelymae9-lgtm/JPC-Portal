<?php

use App\Http\Controllers\Api\V1\ActivityLogs\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\RbacController;
use App\Http\Controllers\Api\V1\AiReportController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Admin\AdminExecutiveDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminDepartmentController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController as AdminReportPortalController;
use App\Http\Controllers\Api\V1\Admin\AdminAiMonitoringController;
use App\Http\Controllers\Api\V1\Admin\AdminEscalationController;
use App\Http\Controllers\Api\V1\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\V1\Admin\MaintenanceController;
use App\Http\Controllers\Api\V1\Admin\SystemHealthController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\Dashboard\ManagementDashboardController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentDashboardController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentEmployeeController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentTaskController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentReportController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentTargetController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentPerformanceController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentEscalationController;
use App\Http\Controllers\Api\V1\Employee\EmployeePortalController;
use App\Http\Controllers\Api\V1\Hr\HrDashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Reports\ReportWorkflowController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TaskAssignmentController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskProgressController;
use App\Http\Controllers\Api\V1\TaskReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\AdminAnnouncementController;
use App\Http\Controllers\Api\V1\Messages\MessageController;
use App\Http\Controllers\Api\V1\Notifications\AnnouncementController;
use App\Http\Controllers\Api\V1\Performance\PerformanceController;
use App\Http\Controllers\Api\V1\Performance\AchievementController;
use App\Http\Controllers\Api\V1\Reporting\ReportingController;
use App\Http\Controllers\Api\V1\Reporting\AnalyticsController;
use App\Http\Controllers\Api\V1\Reporting\ReportExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All JPC Portal APIs are versioned and mounted under /api/v1.
| Future modules (employees, departments, targets, tasks, reports, AI,
| analytics, notifications, etc.) must register their routes inside this
| versioned group and follow the same conventions.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');

        Route::post('logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum')
            ->name('logout');

        Route::get('me', [AuthController::class, 'me'])
            ->middleware('auth:sanctum')
            ->name('me');
    });

    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'permission:settings.manage'])
        ->group(function () {
            Route::get('rbac', RbacController::class)->name('admin.rbac');
        });

    Route::prefix('admin/dashboard')
        ->middleware(['auth:sanctum', 'role:admin,hr,department_head'])
        ->name('admin.dashboard.')
        ->group(function () {
            Route::get('/options', [ManagementDashboardController::class, 'options'])->name('options');
            Route::get('/summary', [ManagementDashboardController::class, 'summary'])->name('summary');
            Route::get('/employees', [ManagementDashboardController::class, 'employees'])->name('employees');
            Route::get('/employees/{employee}/progress', [ManagementDashboardController::class, 'employeeProgress'])->name('employee-progress');
            Route::get('/departments', [ManagementDashboardController::class, 'departments'])->name('departments');
            Route::get('/tasks', [ManagementDashboardController::class, 'tasks'])->name('tasks');
            Route::get('/overdue', [ManagementDashboardController::class, 'overdue'])->name('overdue');
            Route::get('/review-queue', [ManagementDashboardController::class, 'reviewQueue'])->name('review-queue');
            Route::get('/monthly-progress', [ManagementDashboardController::class, 'monthlyProgress'])->name('monthly-progress');
            Route::get('/monthly-achievement', [ManagementDashboardController::class, 'monthlyAchievement'])->name('monthly-achievement');
            Route::get('/target-vs-actual', [ManagementDashboardController::class, 'targetVsActual'])->name('target-vs-actual');
        });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::get('/', [TaskController::class, 'index'])->name('index');
            Route::post('/', [TaskController::class, 'store'])->name('store');

            // Static routes must be declared before the {task} wildcard.
            Route::get('/summary', [TaskController::class, 'summary'])->name('summary');
            Route::get('/monthly-progress', [TaskController::class, 'monthlyProgress'])->name('monthly-progress');
            Route::get('/me', [TaskController::class, 'myTasks'])->name('me.tasks');
            Route::get('/me/summary', [TaskController::class, 'myTasksSummary'])->name('me.tasks.summary');
            Route::get('/employees/{employee}/tasks', [TaskController::class, 'employeeTasks'])->name('employees.tasks');

            Route::get('/{task}', [TaskController::class, 'show'])->name('show');
            Route::put('/{task}', [TaskController::class, 'update'])->name('update');
            Route::patch('/{task}', [TaskController::class, 'update'])->name('patch');
            Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
            Route::post('/{task}/cancel', [TaskController::class, 'cancel'])->name('cancel');
            Route::get('/{task}/assignments', [TaskAssignmentController::class, 'index'])->name('task.assignments.index');
            Route::post('/{task}/assignments', [TaskAssignmentController::class, 'store'])->name('task.assignments.store');
        });

        Route::prefix('task-assignments')->name('task-assignments.')->group(function () {
            Route::get('/{assignment}', [TaskAssignmentController::class, 'show'])->name('show');
            Route::post('/{assignment}/accept', [TaskAssignmentController::class, 'accept'])->name('accept');
            Route::post('/{assignment}/start', [TaskAssignmentController::class, 'start'])->name('start');
            Route::post('/{assignment}/submit', [TaskAssignmentController::class, 'submit'])->name('submit');
            Route::post('/{assignment}/review', [TaskAssignmentController::class, 'review'])->name('review');
            Route::post('/{assignment}/resubmit', [TaskAssignmentController::class, 'resubmit'])->name('resubmit');
            Route::post('/{assignment}/progress', [TaskProgressController::class, 'store'])->name('progress.store');
            Route::get('/{assignment}/progress', [TaskProgressController::class, 'index'])->name('progress.index');
            Route::get('/{assignment}/reviews', [TaskReviewController::class, 'index'])->name('reviews.index');
        });

        Route::prefix('me')->name('me.')->group(function () {
            Route::get('/tasks', [TaskController::class, 'myTasks'])->name('tasks');
            Route::get('/tasks/summary', [TaskController::class, 'myTasksSummary'])->name('tasks.summary');
            Route::get('/tasks/upcoming', [TaskController::class, 'myUpcomingTasks'])->name('tasks.upcoming');
        });

        Route::prefix('notifications')
            ->middleware('permission:notifications.view')
            ->middleware('throttle:100,1')
            ->name('notifications.')
            ->group(function () {
                // Static routes must be declared before the {notification} wildcard.
                Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
                Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');

                Route::prefix('preferences')
                    ->name('preferences.')
                    ->group(function () {
                        Route::get('/', [NotificationPreferenceController::class, 'index'])
                            ->name('index')
                            ->middleware('permission:notifications.view');
                        Route::put('/', [NotificationPreferenceController::class, 'update'])
                            ->name('update')
                            ->middleware('permission:notifications.view');
                    });

                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');
                Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
                Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
            });

        Route::prefix('ai-processing')
            ->middleware('permission:reports.view')
            ->name('ai-processing.')
            ->group(function () {
                Route::get('/dashboard', [AiReportController::class, 'dashboard'])
                    ->name('dashboard');
                Route::get('/{report}', [AiReportController::class, 'show'])
                    ->name('show');
                Route::post('/{report}/documents', [AiReportController::class, 'uploadDocument'])
                    ->name('documents.upload');
                Route::post('/{report}/process', [AiReportController::class, 'process'])
                    ->name('process');
                Route::post('/{report}/ocr', [AiReportController::class, 'ocr'])
                    ->name('ocr');
                Route::post('/{report}/analyze', [AiReportController::class, 'analyze'])
                    ->name('analyze');
                Route::get('/{report}/ai-summary', [AiReportController::class, 'summary'])
                    ->name('summary');
                Route::get('/{report}/ai-validation', [AiReportController::class, 'validation'])
                    ->name('validation');
                Route::get('/{report}/references', [AiReportController::class, 'references'])
                    ->name('references');
                Route::get('/{report}/similarity', [AiReportController::class, 'similarity'])
                    ->name('similarity');
                Route::get('/{report}/findings', [AiReportController::class, 'findings'])
                    ->name('findings');
                Route::post('/{report}/findings/{finding}/review', [AiReportController::class, 'reviewFinding'])
                    ->name('findings.review');
                Route::post('/{report}/review', [AiReportController::class, 'review'])
                    ->name('review');
                Route::post('/{report}/retry', [AiReportController::class, 'retry'])
                    ->name('retry');
                Route::get('/{report}/versions/compare', [AiReportController::class, 'compare'])
                    ->name('versions.compare');
                Route::get('/{report}/processing-status', [AiReportController::class, 'show'])
                    ->name('processing-status');
            });

        Route::prefix('activity-logs')
            ->middleware('permission:activity_logs.view')
            ->name('activity-logs.')
            ->group(function () {
                Route::get('/', [ActivityLogController::class, 'index'])->name('index');
                Route::get('/{activityLog}', [ActivityLogController::class, 'show'])->name('show');
                // Immutability: explicit routes so modification attempts yield 403.
                Route::put('/{activityLog}', [ActivityLogController::class, 'immutable'])->name('immutable');
                Route::post('/{activityLog}', [ActivityLogController::class, 'immutable'])->name('immutable-post');
                Route::delete('/{activityLog}', [ActivityLogController::class, 'immutable'])->name('immutable-delete');
            });

        Route::prefix('employees')->name('employees.')->group(function () {
            Route::get('/progress', [SummaryController::class, 'employeeProgress'])->name('progress');
            Route::get('/monthly-achievement', [SummaryController::class, 'monthlyAchievement'])->name('monthly-achievement');
        });

        Route::prefix('departments')->name('departments.')->group(function () {
            Route::get('/progress', [SummaryController::class, 'departmentProgress'])->name('progress');
        });

        // Reports & Analytics (Phase 3.3-J)
        //
        // Employees (reports.view) reach their own performance/achievement
        // surfaces and their own achievement export. Every other report and
        // export lives behind `analytics.view` (admin/hr/department_head) and
        // is additionally re-scoped server-side: dept heads are locked to
        // their own department, employees to their own record.
        Route::prefix('reports')->name('reports.')->group(function () {
            // Static routes before wildcards.
            Route::get('/me/performance', [ReportController::class, 'myPerformance'])
                ->middleware('permission:reports.view')
                ->name('me.performance');

            Route::get('/achievements/employees/{employee}', [ReportController::class, 'achievementsEmployee'])
                ->middleware('permission:reports.view')
                ->name('achievements.employee');

            Route::get('/export/achievements', [ReportController::class, 'exportAchievements'])
                ->middleware(['permission:reports.view', 'throttle:reports-export'])
                ->name('export.achievements');

            Route::get('/options', [ReportController::class, 'options'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('options');

            Route::get('/task-completion', [ReportController::class, 'taskCompletion'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('task-completion');

            Route::get('/employees', [ReportController::class, 'employeePerformance'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('employees');

            Route::get('/employees/{employee}', [ReportController::class, 'employeePerformanceDetail'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('employees.show');

            Route::get('/departments', [ReportController::class, 'departmentPerformance'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('departments');

            Route::get('/departments/{department}', [ReportController::class, 'departmentPerformanceDetail'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('departments.show');

            Route::get('/achievements/monthly', [ReportController::class, 'achievementsMonthly'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('achievements.monthly');

            Route::get('/target-vs-actual', [ReportController::class, 'targetVsActual'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('target-vs-actual');

            Route::get('/trend/monthly-completions', [ReportController::class, 'trend'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('trend');

            Route::get('/top-performers', [ReportController::class, 'topPerformers'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('top-performers');

            Route::get('/overview', [ReportController::class, 'overview'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('overview');

            Route::get('/leaderboard', [ReportController::class, 'leaderboard'])
                ->middleware(['permission:analytics.view', 'throttle:reports'])
                ->name('leaderboard');

            Route::prefix('export')->name('export.')->middleware('throttle:reports-export')->group(function () {
                Route::get('/task-completion', [ReportController::class, 'exportTaskCompletion'])
                    ->middleware('permission:analytics.view')
                    ->name('task-completion');

                Route::get('/employee-performance', [ReportController::class, 'exportEmployeePerformance'])
                    ->middleware('permission:analytics.view')
                    ->name('employee-performance');

                Route::get('/department-performance', [ReportController::class, 'exportDepartmentPerformance'])
                    ->middleware('permission:analytics.view')
                    ->name('department-performance');
            });

            // Phase 5 — Report Submission, Review, Approval & Version Control
            Route::post('/', [ReportWorkflowController::class, 'storeDraft'])
                ->middleware('permission:reports.view')
                ->name('draft.store');

            Route::put('/{report}', [ReportWorkflowController::class, 'updateDraft'])
                ->middleware('permission:reports.view')
                ->name('draft.update');

            Route::post('/{report}/submit', [ReportWorkflowController::class, 'submit'])
                ->middleware('permission:reports.view')
                ->name('submit');

            Route::post('/{report}/revision', [ReportWorkflowController::class, 'submitRevision'])
                ->middleware('permission:reports.view')
                ->name('revision');

            Route::post('/{report}/request-revision', [ReportWorkflowController::class, 'requestRevision'])
                ->middleware('role:department_head,admin')
                ->name('request-revision');

            Route::post('/{report}/approve', [ReportWorkflowController::class, 'approve'])
                ->middleware('role:department_head,admin')
                ->name('approve');

            Route::post('/{report}/reject', [ReportWorkflowController::class, 'reject'])
                ->middleware('role:department_head,admin')
                ->name('reject');

            Route::post('/{report}/cancel', [ReportWorkflowController::class, 'cancel'])
                ->middleware('permission:reports.view')
                ->name('cancel');

            Route::post('/{report}/archive', [ReportWorkflowController::class, 'archive'])
                ->middleware('role:admin')
                ->name('archive');

            Route::get('/{report}/timeline', [ReportWorkflowController::class, 'timeline'])
                ->middleware('permission:reports.view')
                ->name('timeline');

            Route::get('/{report}/versions', [ReportWorkflowController::class, 'versions'])
                ->middleware('permission:reports.view')
                ->name('versions.index');

            Route::get('/{report}/versions-compare', [ReportWorkflowController::class, 'compareVersions'])
                ->middleware('permission:reports.view')
                ->name('versions.compare');

            Route::get('/{report}/versions/{versionNumber}', [ReportWorkflowController::class, 'versionDetail'])
                ->middleware('permission:reports.view')
                ->name('versions.show');

            Route::get('/{report}/comments', [ReportWorkflowController::class, 'comments'])
                ->middleware('permission:reports.view')
                ->name('comments.index');

            Route::post('/{report}/comments', [ReportWorkflowController::class, 'storeComment'])
                ->middleware('role:department_head,admin,hr')
                ->name('comments.store');

            Route::post('/{report}/comments/{comment}/resolve', [ReportWorkflowController::class, 'resolveComment'])
                ->middleware('permission:reports.view')
                ->name('comments.resolve');
        });

        // Phase 9 — Advanced Reporting, Analytics, Export & Management Intelligence
        //
        // Self-service employee report lives behind reports.view; every other
        // management report and all analytics behind analytics.view. Role
        // scoping is enforced server-side by ResolvesReportingScope.
        Route::prefix('reports')->name('phase9.reports.')->middleware('throttle:reports')->group(function () {
            Route::get('/employee', [ReportingController::class, 'employee'])
                ->middleware('permission:reports.view')
                ->name('employee');

            Route::get('/department', [ReportingController::class, 'department'])
                ->middleware('permission:analytics.view')
                ->name('department');

            Route::get('/institution', [ReportingController::class, 'institution'])
                ->middleware('permission:analytics.view')
                ->name('institution');

            Route::get('/monthly', [ReportingController::class, 'monthly'])
                ->middleware('permission:analytics.view')
                ->name('monthly');

            Route::get('/achievements', [ReportingController::class, 'achievements'])
                ->middleware('permission:analytics.view')
                ->name('achievements');

            Route::get('/late', [ReportingController::class, 'late'])
                ->middleware('permission:analytics.view')
                ->name('late');

            Route::get('/pending', [ReportingController::class, 'pending'])
                ->middleware('permission:analytics.view')
                ->name('pending');

            Route::get('/targets', [ReportingController::class, 'targets'])
                ->middleware('permission:analytics.view')
                ->name('targets');

            Route::get('/data-quality', [ReportingController::class, 'dataQuality'])
                ->middleware('permission:analytics.view')
                ->name('data-quality');

            Route::get('/management-summary', [ReportingController::class, 'managementSummary'])
                ->middleware('permission:analytics.view')
                ->name('management-summary');

            Route::post('/comparison', [ReportingController::class, 'comparison'])
                ->middleware('permission:analytics.view')
                ->name('comparison');
        });

        Route::prefix('analytics')
            ->name('phase9.analytics.')
            ->middleware(['permission:analytics.view', 'throttle:reports'])
            ->group(function () {
                Route::get('/performance-trend', [AnalyticsController::class, 'performanceTrend'])->name('performance-trend');
                Route::get('/completion-trend', [AnalyticsController::class, 'completionTrend'])->name('completion-trend');
                Route::get('/submission-trend', [AnalyticsController::class, 'submissionTrend'])->name('submission-trend');
                Route::post('/department-comparison', [AnalyticsController::class, 'departmentComparison'])->name('department-comparison');
                Route::post('/employee-comparison', [AnalyticsController::class, 'employeeComparison'])->name('employee-comparison');
                Route::post('/period-comparison', [AnalyticsController::class, 'periodComparison'])->name('period-comparison');
                Route::get('/rankings', [AnalyticsController::class, 'rankings'])->name('rankings');
                Route::get('/performance-distribution', [AnalyticsController::class, 'performanceDistribution'])->name('performance-distribution');
            });

        // Phase 9 — Async report export pipeline
        Route::prefix('reports/exports')
            ->name('phase9.exports.')
            ->middleware(['permission:reports.view', 'throttle:reports-export'])
            ->group(function () {
                Route::get('/', [ReportExportController::class, 'index'])->name('index');
                Route::post('/', [ReportExportController::class, 'queue'])->name('store');
                Route::get('/{export}', [ReportExportController::class, 'show'])->name('show');
                Route::get('/{export}/download', [ReportExportController::class, 'download'])->name('download');
            });

        // Phase 6 — Performance Monitoring, KPI, Productivity Score & Monthly Achievements
        Route::prefix('performance')->name('performance.')->group(function () {
            Route::get('/me', [PerformanceController::class, 'myPerformance'])
                ->middleware('permission:reports.view')
                ->name('me');

            Route::get('/me/history', [PerformanceController::class, 'myHistory'])
                ->middleware('permission:reports.view')
                ->name('me.history');

            Route::get('/', [PerformanceController::class, 'index'])
                ->middleware('permission:analytics.view')
                ->name('index');

            Route::get('/rankings', [PerformanceController::class, 'rankings'])
                ->middleware('permission:reports.view')
                ->name('rankings');

            Route::get('/employees/{employee}', [PerformanceController::class, 'show'])
                ->middleware('permission:reports.view')
                ->name('employees.show');

            Route::get('/employees/{employee}/history', [PerformanceController::class, 'show'])
                ->middleware('permission:reports.view')
                ->name('employees.history');

            Route::post('/recalculate', [PerformanceController::class, 'recalculate'])
                ->middleware('role:admin,hr,department_head')
                ->name('recalculate');

            Route::get('/export', [PerformanceController::class, 'export'])
                ->middleware(['permission:analytics.view', 'throttle:reports-export'])
                ->name('export');
        });

        Route::prefix('achievements')->name('achievements.')->group(function () {
            Route::get('/', [AchievementController::class, 'index'])
                ->middleware('permission:reports.view')
                ->name('index');

            Route::get('/me', [AchievementController::class, 'myAchievements'])
                ->middleware('permission:reports.view')
                ->name('me');

            Route::get('/monthly', [AchievementController::class, 'monthly'])
                ->middleware('permission:reports.view')
                ->name('monthly');
        });

        // Employee Portal (Phase 3.3-V) — self-service surface keyed off the
        // authenticated request user; every handler delegates to the service.
        Route::prefix('employee')->name('employee.')->group(function () {
            Route::get('/dashboard', [EmployeePortalController::class, 'dashboard'])
                ->middleware('permission:reports.view')->name('dashboard');

            Route::get('/tasks', [EmployeePortalController::class, 'tasks'])
                ->middleware('permission:reports.view')->name('tasks.index');
            Route::get('/tasks/{taskAssignment}', [EmployeePortalController::class, 'task'])
                ->middleware('permission:reports.view')->name('tasks.show');
            Route::post('/tasks/{taskAssignment}/progress', [EmployeePortalController::class, 'updateTaskProgress'])
                ->middleware('permission:reports.view')->name('tasks.progress');
            Route::post('/tasks/{taskAssignment}/submit', [EmployeePortalController::class, 'submitTask'])
                ->middleware('permission:reports.view')->name('tasks.submit');

            Route::get('/reports', [EmployeePortalController::class, 'reports'])
                ->middleware('permission:reports.view')->name('reports.index');
            Route::post('/reports', [EmployeePortalController::class, 'createReport'])
                ->middleware('permission:reports.view')->name('reports.store');
            Route::get('/reports/{report}', [EmployeePortalController::class, 'report'])
                ->middleware('permission:reports.view')->name('reports.show');
            Route::put('/reports/{report}', [EmployeePortalController::class, 'updateReport'])
                ->middleware('permission:reports.view')->name('reports.update');
            Route::post('/reports/{report}/submit', [EmployeePortalController::class, 'submitReport'])
                ->middleware('permission:reports.view')->name('reports.submit');
            Route::post('/reports/{report}/revision', [EmployeePortalController::class, 'createRevision'])
                ->middleware('permission:reports.view')->name('reports.revision');

            Route::get('/calendar', [EmployeePortalController::class, 'calendar'])
                ->middleware('permission:reports.view')->name('calendar');

            Route::get('/performance', [EmployeePortalController::class, 'performance'])
                ->middleware('permission:reports.view')->name('performance');

            Route::get('/targets', [EmployeePortalController::class, 'targets'])
                ->middleware('permission:reports.view')->name('targets');

            Route::get('/achievements', [EmployeePortalController::class, 'achievements'])
                ->middleware('permission:reports.view')->name('achievements');

            Route::get('/notifications', [EmployeePortalController::class, 'notifications'])
                ->middleware('permission:reports.view')->name('notifications');

            Route::get('/profile', [EmployeePortalController::class, 'profile'])
                ->middleware('permission:reports.view')->name('profile.show');
            Route::put('/profile', [EmployeePortalController::class, 'updateProfile'])
                ->middleware('permission:reports.view')->name('profile.update');

            Route::put('/security/password', [EmployeePortalController::class, 'changePassword'])
                ->middleware('permission:reports.view')->name('security.password');
        });
        // HR Portal (Phase 3.3-W) Ã¢â‚¬â€ HR-scoped surface, NOT admin.
        Route::prefix('hr')->name('hr.')->middleware('permission:hr.dashboard.view')->group(function () {
            Route::get('/dashboard', [HrDashboardController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('/dashboard/options', [HrDashboardController::class, 'options'])
                ->name('options');
            Route::get('/summary', [HrDashboardController::class, 'summary'])
                ->name('summary');
            Route::get('/employees', [HrDashboardController::class, 'employees'])
                ->name('employees');
            Route::get('/monitoring', [HrDashboardController::class, 'monitoring'])
                ->name('monitoring');
            Route::get('/reports', [HrDashboardController::class, 'reports'])
                ->name('reports');
            Route::get('/achievements', [HrDashboardController::class, 'achievements'])
                ->name('achievements');
        });
        // Department Head Portal (Phase 3.3-X)
        Route::prefix('department-head')->name('department-head.')->middleware('role:department_head,admin')->group(function () {
            Route::get('/dashboard', [DepartmentDashboardController::class, 'dashboard'])->name('dashboard');
            Route::get('/employees', [DepartmentEmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/{employee}', [DepartmentEmployeeController::class, 'show'])->name('employees.show');

            Route::get('/tasks', [DepartmentTaskController::class, 'index'])->name('tasks.index');
            Route::post('/tasks', [DepartmentTaskController::class, 'store'])->name('tasks.store');
            Route::get('/tasks/{task}', [DepartmentTaskController::class, 'show'])->name('tasks.show');
            Route::patch('/tasks/{task}', [DepartmentTaskController::class, 'update'])->name('tasks.update');
            Route::post('/tasks/{task}/assign', [DepartmentTaskController::class, 'assign'])->name('tasks.assign');
            Route::post('/tasks/{task}/cancel', [DepartmentTaskController::class, 'cancel'])->name('tasks.cancel');

            Route::get('/reports', [DepartmentReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/{report}', [DepartmentReportController::class, 'show'])->name('reports.show');
            Route::post('/reports/{report}/review', [DepartmentReportController::class, 'review'])->name('reports.review');

            Route::get('/targets', [DepartmentTargetController::class, 'index'])->name('targets.index');
            Route::get('/performance', [DepartmentPerformanceController::class, 'performance'])->name('performance.index');
            Route::get('/achievements', [DepartmentPerformanceController::class, 'achievements'])->name('achievements.index');

            Route::get('/escalations', [DepartmentEscalationController::class, 'index'])->name('escalations.index');
            Route::post('/escalations', [DepartmentEscalationController::class, 'store'])->name('escalations.store');
        });
        // Administration Portal (Phase 3.3-Y)
        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
            Route::get('/executive-dashboard', [AdminExecutiveDashboardController::class, 'dashboard'])->name('executive-dashboard');
            Route::get('/departments', [AdminDepartmentController::class, 'index'])->name('departments.index');
            Route::get('/departments/{department}', [AdminDepartmentController::class, 'show'])->name('departments.show');

            Route::get('/reports', [AdminReportPortalController::class, 'index'])->name('reports.index');
            Route::get('/reports/{report}', [AdminReportPortalController::class, 'show'])->name('reports.show');
            Route::post('/reports/{report}/override', [AdminReportPortalController::class, 'override'])->name('reports.override');

            Route::get('/ai/status', [AdminAiMonitoringController::class, 'status'])->name('ai.status');
            Route::get('/ai/ocr', [AdminAiMonitoringController::class, 'ocr'])->name('ai.ocr');
            Route::post('/ai/retry/{job}', [AdminAiMonitoringController::class, 'retry'])->name('ai.retry');

            Route::get('/escalations', [AdminEscalationController::class, 'index'])->name('escalations.index');
            Route::post('/escalations/{escalation}/resolve', [AdminEscalationController::class, 'resolve'])->name('escalations.resolve');

            // Phase 3.3-Z — Admin Notification Monitoring
            Route::get('/notifications/metrics', [AdminNotificationController::class, 'metrics'])->name('notifications.metrics');
            Route::get('/notifications/logs', [AdminNotificationController::class, 'logs'])->name('notifications.logs');
            Route::post('/notifications/{notificationLog}/retry', [AdminNotificationController::class, 'retry'])->name('notifications.retry');

            // Phase 3.3-Z — Admin Announcements
            Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
            Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->name('announcements.update');
            Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
            Route::post('/announcements/{announcement}/publish', [AdminAnnouncementController::class, 'publish'])->name('announcements.publish');

            // Phase 13 — System Health, Maintenance & Feedback (admin)
            Route::get('/system-health', [SystemHealthController::class, 'index'])->name('system-health');
            Route::get('/system-health/dashboard', [SystemHealthController::class, 'dashboard'])->name('system-health.dashboard');
            Route::get('/system-health/database', [SystemHealthController::class, 'database'])->name('system-health.database');
            Route::get('/system-health/queue', [SystemHealthController::class, 'queue'])->name('system-health.queue');
            Route::get('/system-health/storage', [SystemHealthController::class, 'storage'])->name('system-health.storage');
            Route::get('/system-health/ai', [SystemHealthController::class, 'ai'])->name('system-health.ai');
            Route::get('/system-health/ocr', [SystemHealthController::class, 'ocr'])->name('system-health.ocr');
            Route::get('/system-health/history', [SystemHealthController::class, 'history'])->name('system-health.history');

            Route::get('/maintenance/logs', [MaintenanceController::class, 'index'])->name('maintenance.logs.index');
            Route::post('/maintenance/logs', [MaintenanceController::class, 'store'])->name('maintenance.logs.store');
            Route::get('/maintenance/logs/{maintenanceLog}', [MaintenanceController::class, 'show'])->name('maintenance.logs.show');

            Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
            Route::get('/feedback/ai', [AdminFeedbackController::class, 'aiIndex'])->name('feedback.ai');
            Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
            Route::patch('/feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedback.update');
        });

        // Phase 3.3-Z — Internal Messaging
        Route::prefix('messages')
            ->middleware('permission:notifications.view')
            ->middleware('throttle:60,1')
            ->name('messages.')
            ->group(function () {
                Route::get('/conversations', [MessageController::class, 'conversations'])->name('conversations');
                Route::get('/conversations/{conversation}', [MessageController::class, 'show'])->name('conversations.show');
                Route::post('/', [MessageController::class, 'store'])->name('store');
                Route::post('/{message}/read', [MessageController::class, 'markRead'])->name('read');
                Route::get('/recipients', [MessageController::class, 'recipients'])->name('recipients');
            });

        // Phase 3.3-Z — Active Announcements (user-facing)
        Route::get('/announcements/active', [AnnouncementController::class, 'active'])
            ->middleware('permission:notifications.view')
            ->name('announcements.active');

        // Phase 13 — User Feedback (any authenticated role) and AI quality feedback.
        // AI feedback never mutates the original AI result or performance scores.
        Route::prefix('feedback')->name('feedback.')->group(function () {
            Route::post('/', [FeedbackController::class, 'store'])->name('store');
            Route::get('/mine', [FeedbackController::class, 'mine'])->name('mine');
            Route::post('/ai', [FeedbackController::class, 'storeAiFeedback'])->name('ai');
        });
    // Phase 7 — AI Chat Assistant & Intelligent Analytics
    Route::prefix('ai')
        ->name('ai.')
        ->group(function () {
            Route::post('/chat', [\App\Http\Controllers\Api\V1\Ai\AiChatController::class, 'send'])->name('chat.send');

            Route::prefix('conversations')->name('conversations.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Ai\AiConversationController::class, 'index'])->name('index');
                Route::post('/', [\App\Http\Controllers\Api\V1\Ai\AiConversationController::class, 'store'])->name('store');
                Route::get('/{conversation}', [\App\Http\Controllers\Api\V1\Ai\AiConversationController::class, 'show'])->name('show');
                Route::delete('/{conversation}', [\App\Http\Controllers\Api\V1\Ai\AiConversationController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('insights')->name('insights.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Ai\AiInsightController::class, 'index'])->name('index');
                Route::get('/performance', [\App\Http\Controllers\Api\V1\Ai\AiInsightController::class, 'performance'])->name('performance');
                Route::get('/reports', [\App\Http\Controllers\Api\V1\Ai\AiInsightController::class, 'reports'])->name('reports');
            });

            Route::post('/reports/{report}/summarize', [\App\Http\Controllers\Api\V1\Ai\AiReportController::class, 'summarize'])->name('reports.summarize');
        });

        // Phase 8 — Data Quality, Validation & Integrity Monitoring
        Route::prefix('data-quality')
            ->name('data-quality.')
            ->group(function () {
                Route::get('/dashboard', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityController::class, 'dashboard'])->name('dashboard');
                Route::get('/statistics', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityController::class, 'statistics'])->name('statistics');
                Route::get('/trends', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityController::class, 'trends'])->name('trends');
                Route::get('/export', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityController::class, 'export'])->name('export');

                Route::prefix('issues')->name('issues.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityIssueController::class, 'index'])->name('index');
                    Route::get('/{issue}', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityIssueController::class, 'show'])->name('show');
                    Route::post('/{issue}/review', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityIssueController::class, 'review'])->name('review');
                    Route::post('/{issue}/confirm', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityIssueController::class, 'confirm'])->name('confirm');
                    Route::post('/{issue}/dismiss', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityIssueController::class, 'dismiss'])->name('dismiss');
                    Route::post('/{issue}/resolve', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityIssueController::class, 'resolve'])->name('resolve');
                });

                Route::prefix('scans')->name('scans.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityScanController::class, 'index'])->name('index');
                    Route::post('/', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityScanController::class, 'store'])->name('store');
                    Route::get('/{scan}', [\App\Http\Controllers\Api\V1\DataQuality\DataQualityScanController::class, 'show'])->name('show');
                });
            });
    });
});


