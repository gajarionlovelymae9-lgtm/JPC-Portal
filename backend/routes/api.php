<?php

use App\Http\Controllers\Api\V1\ActivityLogs\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\AdminAchievementController;
use App\Http\Controllers\Api\V1\Admin\AdminAiAssistantController;
use App\Http\Controllers\Api\V1\Admin\AdminAiMonitoringController;
use App\Http\Controllers\Api\V1\Admin\AdminAnnouncementController;
use App\Http\Controllers\Api\V1\Admin\AdminDepartmentController;
use App\Http\Controllers\Api\V1\Admin\AdminEmployeeProgressController;
use App\Http\Controllers\Api\V1\Admin\AdminEscalationController;
use App\Http\Controllers\Api\V1\Admin\AdminExecutiveDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminGovernanceController;
use App\Http\Controllers\Api\V1\Admin\AdminInsightController;
use App\Http\Controllers\Api\V1\Admin\AdminKpiTargetController;
use App\Http\Controllers\Api\V1\Admin\AdminMonitoringController;
use App\Http\Controllers\Api\V1\Admin\AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\AdminPeopleManagementController;
use App\Http\Controllers\Api\V1\Admin\AdminPerformanceOverviewController;
use App\Http\Controllers\Api\V1\Admin\AdminPlatformStatusController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController as AdminReportPortalController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminTargetAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\AdminTargetController;
use App\Http\Controllers\Api\V1\Admin\AdminUserManagementController;
use App\Http\Controllers\Api\V1\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\V1\Admin\MaintenanceController;
use App\Http\Controllers\Api\V1\Admin\RbacController;
use App\Http\Controllers\Api\V1\Admin\SystemHealthController;
use App\Http\Controllers\Api\V1\Ai\AiChatController;
use App\Http\Controllers\Api\V1\Ai\AiConversationController;
use App\Http\Controllers\Api\V1\Ai\AiInsightController;
use App\Http\Controllers\Api\V1\Ai\AiLanguagePreferenceController;
use App\Http\Controllers\Api\V1\AiReportController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Dashboard\ManagementDashboardController;
use App\Http\Controllers\Api\V1\DataQuality\DataQualityController;
use App\Http\Controllers\Api\V1\DataQuality\DataQualityIssueController;
use App\Http\Controllers\Api\V1\DataQuality\DataQualityScanController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentDashboardController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentEmployeeController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentEscalationController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentPerformanceController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentRecommendationController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentReportController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentTargetController;
use App\Http\Controllers\Api\V1\DepartmentHead\DepartmentTaskController;
use App\Http\Controllers\Api\V1\Employee\EmployeeAIInsightsController;
use App\Http\Controllers\Api\V1\Employee\EmployeePortalController;
use App\Http\Controllers\Api\V1\Employee\EmployeeTargetController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Hr\HrAchievementController;
use App\Http\Controllers\Api\V1\Hr\HrCalendarController;
use App\Http\Controllers\Api\V1\Hr\HrDashboardController;
use App\Http\Controllers\Api\V1\Hr\HrEmployeeController;
use App\Http\Controllers\Api\V1\Hr\HrTargetController;
use App\Http\Controllers\Api\V1\Messages\MessageController;
use App\Http\Controllers\Api\V1\Notifications\AnnouncementController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Performance\AchievementController;
use App\Http\Controllers\Api\V1\Performance\PerformanceController;
use App\Http\Controllers\Api\V1\PlanningController;
use App\Http\Controllers\Api\V1\Reporting\AnalyticsController;
use App\Http\Controllers\Api\V1\Reporting\ReportExportController;
use App\Http\Controllers\Api\V1\Reporting\ReportingController;
use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Reports\ReportWorkflowController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TaskAssignmentController;
use App\Http\Controllers\Api\V1\TaskAttachmentController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskProgressController;
use App\Http\Controllers\Api\V1\TaskReviewController;
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

    // Read-only planning overview (Phase 3/6): Admin and HR inspect the
    // academic-year → annual-plan → department-plan → employee-target chain.
    // No mutating verbs are registered here by design.
    Route::prefix('planning')
        ->middleware(['auth:sanctum', 'permission:planning.view'])
        ->name('planning.')
        ->group(function () {
            Route::get('/', PlanningController::class)->name('overview');
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
            Route::get('/{assignment}/submissions', [TaskAssignmentController::class, 'submissions'])->name('submissions.index');
        });

        Route::prefix('task-attachments')->name('task-attachments.')->group(function () {
            Route::get('/{attachment}/download', [TaskAttachmentController::class, 'download'])->name('download');
        });

        Route::prefix('me')->name('me.')->group(function () {
            Route::get('/tasks', [TaskController::class, 'myTasks'])->name('tasks');
            Route::get('/tasks/summary', [TaskController::class, 'myTasksSummary'])->name('tasks.summary');
            Route::get('/tasks/upcoming', [TaskController::class, 'myUpcomingTasks'])->name('tasks.upcoming');
        });

        Route::prefix('notifications')
            ->middleware(['permission:notifications.view', 'throttle:100,1'])
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

            // Department directory — read-only institution-wide data, available
            // to any role holding `departments.view` (admin and HR). The
            // People Management module reads the same service through these
            // routes; its writes stay admin-only under /admin/departments.
            Route::get('/', [AdminDepartmentController::class, 'index'])
                ->middleware('permission:departments.view')->name('index');
            Route::get('/summary', [AdminDepartmentController::class, 'summary'])
                ->middleware('permission:departments.view')->name('summary');
            Route::get('/options', [AdminDepartmentController::class, 'options'])
                ->middleware('permission:departments.view')->name('options');
            Route::get('/{department}', [AdminDepartmentController::class, 'show'])
                ->middleware('permission:departments.view')->name('show');
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
        //
        // The export *generation* routes are expensive and stay on the tight
        // `reports-export` limiter (10/min). The read-only status surfaces
        // (`index`, `show`) are cheap and are polled by the UI every few
        // seconds while an export is queued — running them through the
        // 10/min bucket made the library list return HTTP 429
        // ("Too many requests. Please wait a moment before trying again.")
        // after 10 polls, i.e. roughly 40 seconds into a single export.
        // They belong to the general `reports` limiter (120/min).
        Route::prefix('reports/exports')
            ->name('phase9.exports.')
            ->middleware('permission:reports.view')
            ->group(function () {
                Route::middleware('throttle:reports')->group(function () {
                    Route::get('/', [ReportExportController::class, 'index'])->name('index');
                    Route::get('/{export}', [ReportExportController::class, 'show'])->name('show');
                });

                Route::middleware('throttle:reports-export')->group(function () {
                    Route::post('/', [ReportExportController::class, 'queue'])->name('store');
                    Route::get('/{export}/download', [ReportExportController::class, 'download'])->name('download');
                    // Retry a failed export without re-submitting the request form.
                    Route::post('/{export}/retry', [ReportExportController::class, 'retry'])->name('retry');
                });
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

            // Earned badges plus tracked progress toward every unearned badge.
            Route::get('/progress', [AchievementController::class, 'myProgress'])
                ->middleware('permission:reports.view')
                ->name('progress');

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
            Route::post('/reports/{report}', [EmployeePortalController::class, 'updateReport'])
                ->middleware('permission:reports.view')->name('reports.update.post');
            Route::post('/reports/{report}/submit', [EmployeePortalController::class, 'submitReport'])
                ->middleware('permission:reports.view')->name('reports.submit');
            Route::post('/reports/{report}/ai-summary', [EmployeePortalController::class, 'generateReportSummary'])
                ->middleware('permission:reports.view')->name('reports.ai-summary');
            Route::get('/reports/{report}/files/{file}', [EmployeePortalController::class, 'downloadReportFile'])
                ->middleware('permission:reports.view')->name('reports.files.download');
            Route::post('/reports/{report}/revision', [EmployeePortalController::class, 'createRevision'])
                ->middleware('permission:reports.view')->name('reports.revision');

            Route::get('/calendar', [EmployeePortalController::class, 'calendar'])
                ->middleware('permission:reports.view')->name('calendar');

            Route::get('/performance', [EmployeePortalController::class, 'performance'])
                ->middleware('permission:reports.view')->name('performance');

            // AI Insights (advisory, derived only from the caller's own records).
            // `reports.view` matches the rest of the self-service surface; the
            // service resolves the employee from the authenticated user so no
            // employee_id is ever accepted, and `throttle:reports` bounds the
            // multi-month KPI aggregation this endpoint performs.
            // AI access restricted to Employee role.
            Route::get('/ai-insights', [EmployeeAIInsightsController::class, 'show'])
                ->middleware(['role:employee', 'ai.access', 'permission:reports.view', 'throttle:reports'])->name('ai-insights');

            // My Targets — employee self-service target workspace (breakdown,
            // milestones, adjustment requests, achievement notes, AI
            // suggestions). The list route keeps its historical URI and name
            // (route contract) but now delegates to EmployeeTargetController.
            // Every route resolves the employee from the authenticated user, so
            // no employee_id is ever accepted and another employee's target is
            // answered as 404. Literal paths are declared before the {target}
            // wildcard.
            Route::get('/targets', [EmployeeTargetController::class, 'index'])
                ->middleware('permission:reports.view')->name('targets');

            // Advisory AI suggestions restricted to the caller's own targets.
            // Mirrors the ai-insights gating (Employee role + JPC AIDE access);
            // throttle:reports bounds the per-target analysis it performs.
            Route::get('/targets/suggestions', [EmployeeTargetController::class, 'suggestions'])
                ->middleware(['role:employee', 'ai.access', 'permission:reports.view', 'throttle:reports'])
                ->name('targets.suggestions');

            Route::get('/targets/{target}', [EmployeeTargetController::class, 'show'])
                ->middleware('permission:reports.view')->name('targets.show');

            // Target breakdown (sub-targets).
            Route::get('/targets/{target}/breakdowns', [EmployeeTargetController::class, 'breakdowns'])
                ->middleware('permission:reports.view')->name('targets.breakdowns.index');
            Route::post('/targets/{target}/breakdowns', [EmployeeTargetController::class, 'storeBreakdown'])
                ->middleware('permission:reports.view')->name('targets.breakdowns.store');
            Route::put('/targets/{target}/breakdowns/{breakdown}', [EmployeeTargetController::class, 'updateBreakdown'])
                ->middleware('permission:reports.view')->name('targets.breakdowns.update');
            Route::delete('/targets/{target}/breakdowns/{breakdown}', [EmployeeTargetController::class, 'destroyBreakdown'])
                ->middleware('permission:reports.view')->name('targets.breakdowns.destroy');

            // Target milestones.
            Route::get('/targets/{target}/milestones', [EmployeeTargetController::class, 'milestones'])
                ->middleware('permission:reports.view')->name('targets.milestones.index');
            Route::post('/targets/{target}/milestones', [EmployeeTargetController::class, 'storeMilestone'])
                ->middleware('permission:reports.view')->name('targets.milestones.store');
            Route::put('/targets/{target}/milestones/{milestone}', [EmployeeTargetController::class, 'updateMilestone'])
                ->middleware('permission:reports.view')->name('targets.milestones.update');
            Route::delete('/targets/{target}/milestones/{milestone}', [EmployeeTargetController::class, 'destroyMilestone'])
                ->middleware('permission:reports.view')->name('targets.milestones.destroy');

            // Target adjustment requests (decision is made by an admin).
            Route::get('/targets/{target}/adjustments', [EmployeeTargetController::class, 'adjustments'])
                ->middleware('permission:reports.view')->name('targets.adjustments.index');
            Route::post('/targets/{target}/adjustments', [EmployeeTargetController::class, 'storeAdjustment'])
                ->middleware('permission:reports.view')->name('targets.adjustments.store');

            // Target achievement notes (author-only management).
            Route::get('/targets/{target}/notes', [EmployeeTargetController::class, 'notes'])
                ->middleware('permission:reports.view')->name('targets.notes.index');
            Route::post('/targets/{target}/notes', [EmployeeTargetController::class, 'storeNote'])
                ->middleware('permission:reports.view')->name('targets.notes.store');
            Route::put('/targets/{target}/notes/{note}', [EmployeeTargetController::class, 'updateNote'])
                ->middleware('permission:reports.view')->name('targets.notes.update');
            Route::delete('/targets/{target}/notes/{note}', [EmployeeTargetController::class, 'destroyNote'])
                ->middleware('permission:reports.view')->name('targets.notes.destroy');

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

            // Employee AI Assistant — conversational, history, saved summaries.
            Route::prefix('ai-assistant')->name('ai-assistant.')->middleware(['role:employee', 'ai.access'])->group(function () {
                Route::get('/usage', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'usage'])->name('usage');
                Route::post('/chat', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'chat'])->name('chat');
                Route::get('/conversations', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'conversations'])->name('conversations.index');
                Route::post('/conversations', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'storeConversation'])->name('conversations.store');
                Route::get('/conversations/{conversation}', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'showConversation'])->name('conversations.show');
                Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'messages'])->name('conversations.messages');
                Route::match(['put', 'patch'], '/conversations/{conversation}', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'updateConversation'])->name('conversations.update');
                Route::delete('/conversations/{conversation}', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'destroyConversation'])->name('conversations.destroy');
                Route::get('/summaries', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'summaries'])->name('summaries.index');
                Route::post('/summaries', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'storeSummary'])->name('summaries.store');
                Route::get('/summaries/{summary}', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'showSummary'])->name('summaries.show');
                Route::delete('/summaries/{summary}', [\App\Http\Controllers\Api\V1\Employee\EmployeeAiAssistantController::class, 'destroySummary'])->name('summaries.destroy');
            });
        });
        // HR Portal (Phase 3.3-W) — HR-scoped surface, NOT admin.
        Route::prefix('hr')->name('hr.')->middleware('permission:hr.dashboard.view')->group(function () {
            Route::get('/dashboard', [HrDashboardController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('/dashboard/options', [HrDashboardController::class, 'options'])
                ->name('options');
            Route::get('/employees', [HrDashboardController::class, 'employees'])
                ->name('employees');

            // Employee management (read-one / create / edit / account status).
            //
            // The directory list above stays on HrDashboardController because it
            // already exists and is contract-tested; these routes add only the
            // operations it does not cover. Each carries the specific
            // `employees.*` permission on top of the group's HR portal check,
            // so holding `hr.dashboard.view` alone is not enough to write.
            Route::get('/employees/duplicate-name', [HrEmployeeController::class, 'duplicateName'])
                ->middleware('permission:employees.view')
                ->name('employees.duplicate-name');
            Route::get('/employees/{employee}', [HrEmployeeController::class, 'show'])
                ->middleware('permission:employees.view')
                ->name('employees.show');
            Route::post('/employees', [HrEmployeeController::class, 'store'])
                ->middleware('permission:employees.create')
                ->name('employees.store');
            Route::put('/employees/{employee}', [HrEmployeeController::class, 'update'])
                ->middleware('permission:employees.update')
                ->name('employees.update');
            Route::patch('/employees/{employee}', [HrEmployeeController::class, 'update'])
                ->middleware('permission:employees.update')
                ->name('employees.patch');
            Route::post('/employees/{employee}/account-status', [HrEmployeeController::class, 'accountStatus'])
                ->middleware('permission:employees.update')
                ->name('employees.account-status');
            Route::get('/monitoring', [HrDashboardController::class, 'monitoring'])
                ->name('monitoring');
            Route::get('/reports', [HrDashboardController::class, 'reports'])
                ->name('reports');
            Route::get('/achievements', [HrDashboardController::class, 'achievements'])
                ->name('achievements');

            // HR institutional calendar — reuses the shared monitoring
            // calendar (deadlines / KPI periods / achievements / published
            // announcements) with full announcement details attached.
            Route::get('/calendar', [HrCalendarController::class, 'index'])
                ->name('calendar');

            // HR target management — CRUD over the shared `employee_targets`
            // chain. Reads reuse AdminKpiTargetService, writes are HR-scoped
            // and permission-gated per verb; `targets.view` alone is not
            // enough to mutate anything.
            Route::prefix('targets')->name('targets.')->group(function () {
                Route::get('/', [HrTargetController::class, 'index'])
                    ->middleware('permission:targets.view')->name('index');
                Route::post('/', [HrTargetController::class, 'store'])
                    ->middleware('permission:targets.create')->name('store');
                Route::get('/{id}', [HrTargetController::class, 'show'])
                    ->middleware('permission:targets.view')->name('show');
                Route::put('/{id}', [HrTargetController::class, 'update'])
                    ->middleware('permission:targets.update')->name('update');
                Route::patch('/{id}', [HrTargetController::class, 'update'])
                    ->middleware('permission:targets.update')->name('patch');
                Route::delete('/{id}', [HrTargetController::class, 'destroy'])
                    ->middleware('permission:targets.delete')->name('destroy');
            });

            Route::prefix('achievement-badges')->name('achievement-badges.')->group(function () {
                Route::get('/', [HrAchievementController::class, 'index'])
                    ->middleware('permission:achievements.view');
                Route::get('/options', [HrAchievementController::class, 'options'])
                    ->middleware('permission:achievements.view');
                Route::get('/reports', [HrAchievementController::class, 'reports'])
                    ->middleware('permission:achievements.view');
                Route::get('/export', [HrAchievementController::class, 'export'])
                    ->middleware(['permission:achievements.view', 'throttle:reports-export']);
                Route::get('/{id}', [HrAchievementController::class, 'show'])
                    ->middleware('permission:achievements.view');
                Route::post('/{id}/verify', [HrAchievementController::class, 'verify'])
                    ->middleware('permission:achievements.manage');
                Route::post('/{id}/reject', [HrAchievementController::class, 'reject'])
                    ->middleware('permission:achievements.manage');
            });
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

            // Recognition recommendations — the head proposes a pending award
            // for an employee of their own department; HR reviews it through
            // the existing verification workflow.
            Route::prefix('achievements/recommendations')->name('achievements.recommendations.')->group(function () {
                Route::get('/', [DepartmentRecommendationController::class, 'index'])->middleware('permission:badges.award')->name('index');
                Route::get('/options', [DepartmentRecommendationController::class, 'options'])->middleware('permission:badges.award')->name('options');
                Route::post('/', [DepartmentRecommendationController::class, 'store'])->middleware('permission:badges.award')->name('store');
            });

            Route::get('/escalations', [DepartmentEscalationController::class, 'index'])->name('escalations.index');
            Route::get('/escalations/summary', [DepartmentEscalationController::class, 'summary'])->name('escalations.summary');
            Route::get('/escalations/options', [DepartmentEscalationController::class, 'options'])->name('escalations.options');
            Route::post('/escalations', [DepartmentEscalationController::class, 'store'])->name('escalations.store');
            Route::get('/escalations/{escalation}', [DepartmentEscalationController::class, 'show'])->name('escalations.show');
        });
        // Administration Portal (Phase 3.3-Y)
        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
            Route::get('/executive-dashboard', [AdminExecutiveDashboardController::class, 'dashboard'])->name('executive-dashboard');
            // People Management — module summary card counts.
            Route::get('/people/summary', [AdminPeopleManagementController::class, 'summary'])->name('people.summary');

            // Roles & Access — catalogue + protected permission sync.
            Route::get('/roles', [AdminRoleController::class, 'index'])
                ->middleware('permission:roles.view')->name('roles.index');
            Route::put('/roles/{role}/permissions', [AdminRoleController::class, 'updatePermissions'])
                ->middleware('permission:permissions.manage')->name('roles.permissions');

            // Department management. Static routes (/summary, /options) are
            // registered ahead of the {department} binding so they are not
            // swallowed by the wildcard. There is deliberately no DELETE:
            // departments are deactivated, never hard-deleted.
            Route::get('/departments/summary', [AdminDepartmentController::class, 'summary'])->name('departments.summary');
            Route::get('/departments/options', [AdminDepartmentController::class, 'options'])->name('departments.options');
            Route::get('/departments', [AdminDepartmentController::class, 'index'])->name('departments.index');
            Route::get('/departments/{department}/eligible-heads', [AdminDepartmentController::class, 'eligibleHeads'])
                ->middleware('permission:departments.view')->name('departments.eligible-heads');
            Route::get('/departments/{department}', [AdminDepartmentController::class, 'show'])->name('departments.show');
            Route::post('/departments', [AdminDepartmentController::class, 'store'])
                ->middleware('permission:departments.create')->name('departments.store');
            Route::put('/departments/{department}', [AdminDepartmentController::class, 'update'])
                ->middleware('permission:departments.update')->name('departments.update');
            Route::post('/departments/{department}/status', [AdminDepartmentController::class, 'status'])
                ->middleware('permission:departments.update')->name('departments.status');
            Route::post('/departments/{department}/head', [AdminDepartmentController::class, 'assignHead'])
                ->middleware('permission:departments.update')->name('departments.head');
            Route::post('/departments/{department}/employees', [AdminDepartmentController::class, 'assignEmployees'])
                ->middleware('permission:departments.update')->name('departments.assign-employees');

            Route::get('/reports', [AdminReportPortalController::class, 'index'])->name('reports.index');
            Route::get('/reports/{report}', [AdminReportPortalController::class, 'show'])->name('reports.show');
            Route::post('/reports/{report}/override', [AdminReportPortalController::class, 'override'])->name('reports.override');

            Route::get('/ai/status', [AdminAiMonitoringController::class, 'status'])->name('ai.status');
            Route::get('/ai/ocr', [AdminAiMonitoringController::class, 'ocr'])->name('ai.ocr');
            Route::post('/ai/retry/{job}', [AdminAiMonitoringController::class, 'retry'])->name('ai.retry');

            Route::get('/escalations', [AdminEscalationController::class, 'index'])->name('escalations.index');
            Route::get('/escalations/summary', [AdminEscalationController::class, 'summary'])->name('escalations.summary');
            Route::get('/escalations/options', [AdminEscalationController::class, 'options'])->name('escalations.options');
            Route::get('/escalations/{escalation}', [AdminEscalationController::class, 'show'])->name('escalations.show');
            Route::post('/escalations/{escalation}/assign', [AdminEscalationController::class, 'assign'])->name('escalations.assign');
            Route::post('/escalations/{escalation}/status', [AdminEscalationController::class, 'updateStatus'])->name('escalations.status');
            Route::post('/escalations/{escalation}/notes', [AdminEscalationController::class, 'addNote'])->name('escalations.notes');

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
            Route::post('/announcements/{announcement}/schedule', [AdminAnnouncementController::class, 'schedule'])->name('announcements.schedule');
            Route::delete('/announcements/{announcement}/schedule', [AdminAnnouncementController::class, 'unschedule'])->name('announcements.unschedule');
            Route::post('/announcements/{announcement}/archive', [AdminAnnouncementController::class, 'archive'])->name('announcements.archive');

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

            // Full Admin Dashboard sections (executive, monitoring, governance)
            Route::get('/platform-status', AdminPlatformStatusController::class)->name('platform-status');
            Route::get('/performance-overview', AdminPerformanceOverviewController::class)->name('performance-overview');
            Route::get('/kpi-targets', AdminKpiTargetController::class)->name('kpi-targets');

            // My Targets — administrative review surface. Reads require
            // `targets.view`, decisions require `targets.update`; both sit inside
            // the role:admin group guard. Literal paths precede the {adjustment}
            // wildcard.
            Route::get('/target-adjustments', [AdminTargetAdjustmentController::class, 'index'])
                ->middleware('permission:targets.view')->name('target-adjustments.index');
            Route::get('/target-adjustments/summary', [AdminTargetAdjustmentController::class, 'summary'])
                ->middleware('permission:targets.view')->name('target-adjustments.summary');
            Route::get('/target-adjustments/{adjustment}', [AdminTargetAdjustmentController::class, 'show'])
                ->middleware('permission:targets.view')->name('target-adjustments.show');
            Route::post('/target-adjustments/{adjustment}/approve', [AdminTargetAdjustmentController::class, 'approve'])
                ->middleware('permission:targets.update')->name('target-adjustments.approve');
            Route::post('/target-adjustments/{adjustment}/reject', [AdminTargetAdjustmentController::class, 'reject'])
                ->middleware('permission:targets.update')->name('target-adjustments.reject');

            // Admin read of a single target with its breakdowns, milestones and
            // achievement notes for review.
            Route::get('/targets/{target}', [AdminTargetController::class, 'show'])
                ->middleware('permission:targets.view')->name('targets.review.show');

            Route::get('/employee-progress', [AdminEmployeeProgressController::class, 'index'])->name('employee-progress.index');
            Route::get('/employee-progress/summary', [AdminEmployeeProgressController::class, 'summary'])->name('employee-progress.summary');
            Route::get('/employee-progress/options', [AdminEmployeeProgressController::class, 'options'])->name('employee-progress.options');
            Route::get('/employee-progress/{employeeId}', [AdminEmployeeProgressController::class, 'show'])->name('employee-progress.show');

            Route::get('/achievements', [AdminAchievementController::class, 'index'])->name('achievements.index');
            Route::get('/achievements/badges', [AdminAchievementController::class, 'badges'])->name('achievements.badges');
            Route::get('/achievements/options', [AdminAchievementController::class, 'options'])->name('achievements.options');

            // Achievement & Badges management (badge definitions, verification,
            // institution-wide summaries, evaluation trigger). Every route
            // still sits inside the role:admin group; the permission middleware
            // mirrors the existing Permissions catalogue so the backend, not
            // the UI, is what enforces each action.
            Route::get('/achievements/badge-definitions', [AdminAchievementController::class, 'badgeDefinitions'])
                ->middleware('permission:badges.view')->name('achievements.badge-definitions.index');
            Route::post('/achievements/badge-definitions', [AdminAchievementController::class, 'storeBadge'])
                ->middleware('permission:badges.create')->name('achievements.badge-definitions.store');
            Route::put('/achievements/badge-definitions/{badge}', [AdminAchievementController::class, 'updateBadge'])
                ->middleware('permission:badges.update')->name('achievements.badge-definitions.update');
            Route::delete('/achievements/badge-definitions/{badge}', [AdminAchievementController::class, 'destroyBadge'])
                ->middleware('permission:badges.delete')->name('achievements.badge-definitions.destroy');

            Route::get('/achievements/awards/{id}', [AdminAchievementController::class, 'awardDetail'])
                ->middleware('permission:achievements.view')->name('achievements.awards.detail');
            Route::post('/achievements/awards/{id}/verify', [AdminAchievementController::class, 'verifyAward'])
                ->middleware('permission:achievements.manage')->name('achievements.awards.verify');
            Route::post('/achievements/awards/{id}/reject', [AdminAchievementController::class, 'rejectAward'])
                ->middleware('permission:achievements.manage')->name('achievements.awards.reject');

            Route::get('/achievements/summaries', [AdminAchievementController::class, 'summaries'])
                ->middleware('permission:achievements.view')->name('achievements.summaries');
            Route::post('/achievements/evaluate', [AdminAchievementController::class, 'evaluate'])
                ->middleware('permission:achievements.manage')->name('achievements.evaluate');

            Route::get('/task-monitoring', [AdminMonitoringController::class, 'tasks'])->name('task-monitoring');
            Route::get('/report-monitoring', [AdminMonitoringController::class, 'reports'])->name('report-monitoring');
            Route::get('/calendar', [AdminMonitoringController::class, 'calendar'])->name('calendar');
            Route::get('/monitoring-options', [AdminMonitoringController::class, 'options'])->name('monitoring-options');

            Route::get('/notifications', [AdminGovernanceController::class, 'notifications'])->name('notifications.index');
            // Notification centre detail actions (View Details / View
            // Recipients / Mark as Read). `notifications-centre` avoids
            // colliding with `/notifications` and the delivery monitor above.
            Route::get('/notifications-centre/{notification}', [AdminGovernanceController::class, 'show'])->name('notifications.show');
            Route::get('/notifications-centre/{notification}/recipients', [AdminGovernanceController::class, 'recipients'])->name('notifications.recipients');
            Route::post('/notifications-centre/{notification}/read', [AdminGovernanceController::class, 'markRead'])->name('notifications.read');
            Route::get('/audit-trail', [AdminGovernanceController::class, 'auditTrail'])->name('audit-trail');
            Route::get('/governance-summary', [AdminGovernanceController::class, 'summary'])->name('governance-summary');
            Route::get('/governance-options', [AdminGovernanceController::class, 'options'])->name('governance-options');

            Route::get('/ai-insights', [AdminInsightController::class, 'index'])->name('ai-insights');
            Route::post('/ai-insights/generate', [AdminInsightController::class, 'generate'])->name('ai-insights.generate');
            Route::get('/ai-insight-options', [AdminInsightController::class, 'options'])->name('ai-insight-options');

            Route::get('/users', [AdminUserManagementController::class, 'index'])->name('users.index');
            Route::post('/users', [AdminUserManagementController::class, 'store'])->name('users.store');
            Route::get('/users/{id}', [AdminUserManagementController::class, 'show'])->name('users.show');
            Route::put('/users/{id}', [AdminUserManagementController::class, 'update'])->name('users.update');
            Route::delete('/users/{id}', [AdminUserManagementController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{id}/reactivate', [AdminUserManagementController::class, 'reactivate'])->name('users.reactivate');
            Route::post('/users/{id}/suspend', [AdminUserManagementController::class, 'suspend'])->name('users.suspend');
            Route::post('/users/{id}/reset-password', [AdminUserManagementController::class, 'resetPassword'])->name('users.reset-password');
        });

        // Phase 3.3-Z — Internal Messaging (§1–§9 People Directory, private & group chat,
        // attachments, read receipts, notifications). Route middleware requires the
        // notifications.view permission (held by all four roles); per-conversation access
        // is additionally enforced by ConversationPolicy inside the controller.
        Route::prefix('messages')
            ->middleware(['permission:notifications.view', 'throttle:120,1'])
            ->name('messages.')
            ->group(function () {
                // Directory + inbox
                Route::get('/recipients', [MessageController::class, 'recipients'])->name('recipients');
                Route::get('/conversations', [MessageController::class, 'conversations'])->name('conversations');
                Route::get('/unread-summary', [MessageController::class, 'unreadSummary'])->name('unread-summary');
                Route::get('/conversations/{conversation}', [MessageController::class, 'show'])->name('conversations.show');

                // Send a direct or existing-group message
                Route::post('/', [MessageController::class, 'store'])->name('store');

                // Group management
                Route::post('/groups', [MessageController::class, 'storeGroup'])->name('groups.store');
                Route::put('/conversations/{conversation}', [MessageController::class, 'updateGroup'])->name('groups.update');
                Route::post('/conversations/{conversation}/participants', [MessageController::class, 'addParticipants'])->name('participants.store');
                Route::delete('/conversations/{conversation}/participants/{user}', [MessageController::class, 'removeParticipant'])->name('participants.destroy');
                Route::post('/conversations/{conversation}/leave', [MessageController::class, 'leave'])->name('leave');

                // Read / archive state
                Route::post('/conversations/{conversation}/read', [MessageController::class, 'markRead'])->name('conversations.read');
                Route::post('/conversations/{conversation}/archive', [MessageController::class, 'archive'])->name('conversations.archive');
                Route::post('/{message}/read', [MessageController::class, 'markMessageRead'])->name('read');

                // Authorised attachment download
                Route::get('/{message}/attachment', [MessageController::class, 'downloadAttachment'])->name('attachment');
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
        // Admin-only JPC AIDE (real LLM, grounded, no fake-provider success responses).
        Route::prefix('admin/ai-assistant')
            ->name('admin.ai-assistant.')
            ->middleware(['role:admin', 'ai.access'])
            ->group(function () {
                Route::get('/usage', [AdminAiAssistantController::class, 'usage'])->name('usage');
                Route::post('/chat', [AdminAiAssistantController::class, 'chat'])->name('chat');
                Route::get('/conversations', [AdminAiAssistantController::class, 'conversations'])->name('conversations.index');
                Route::post('/conversations', [AdminAiAssistantController::class, 'storeConversation'])->name('conversations.store');
                Route::get('/conversations/{conversation}', [AdminAiAssistantController::class, 'showConversation'])->name('conversations.show');
                Route::get('/conversations/{conversation}/messages', [AdminAiAssistantController::class, 'messages'])->name('conversations.messages');
                Route::match(['put', 'patch'], '/conversations/{conversation}', [AdminAiAssistantController::class, 'updateConversation'])->name('conversations.update');
                Route::delete('/conversations/{conversation}', [AdminAiAssistantController::class, 'destroyConversation'])->name('conversations.destroy');
            });

        // Phase 7 — AI Chat Assistant & Intelligent Analytics
        Route::prefix('ai')
            ->name('ai.')
            ->group(function () {
                // JPC AIDE assistant endpoints are restricted to authorized Admin and Employee roles only.
                Route::middleware('ai.access')->group(function () {
                    Route::get('/language-preference', [AiLanguagePreferenceController::class, 'show'])->name('language-preference.show');
                    Route::put('/language-preference', [AiLanguagePreferenceController::class, 'update'])->name('language-preference.update');
                    Route::get('/usage', [AiChatController::class, 'usage'])->name('usage');
                    Route::post('/chat', [AiChatController::class, 'send'])->name('chat.send');

                    Route::prefix('conversations')->name('conversations.')->group(function () {
                        Route::get('/', [AiConversationController::class, 'index'])->name('index');
                        Route::post('/', [AiConversationController::class, 'store'])->name('store');
                        Route::get('/{conversation}', [AiConversationController::class, 'show'])->name('show');
                        Route::get('/{conversation}/messages', [AiConversationController::class, 'messages'])->name('messages');
                        Route::match(['put', 'patch'], '/{conversation}', [AiConversationController::class, 'update'])->name('update');
                        Route::delete('/{conversation}', [AiConversationController::class, 'destroy'])->name('destroy');
                    });
                });

                // Institution-wide AI Insights are Admin-only. HR, Department and
                // other roles must be denied here even if they guess the URL.
                Route::prefix('insights')->name('insights.')->middleware('role:admin')->group(function () {
                    Route::get('/', [AiInsightController::class, 'index'])->name('index');
                    Route::get('/performance', [AiInsightController::class, 'performance'])->name('performance');
                    Route::get('/reports', [AiInsightController::class, 'reports'])->name('reports');
                });

                Route::post('/reports/{report}/summarize', [App\Http\Controllers\Api\V1\Ai\AiReportController::class, 'summarize'])
                    ->middleware('ai.access')
                    ->name('reports.summarize');
            });

        // Phase 8 — Data Quality, Validation & Integrity Monitoring
        Route::prefix('data-quality')
            ->name('data-quality.')
            ->group(function () {
                Route::get('/dashboard', [DataQualityController::class, 'dashboard'])->name('dashboard');
                Route::get('/statistics', [DataQualityController::class, 'statistics'])->name('statistics');
                Route::get('/trends', [DataQualityController::class, 'trends'])->name('trends');
                Route::get('/export', [DataQualityController::class, 'export'])->name('export');

                Route::prefix('issues')->name('issues.')->group(function () {
                    Route::get('/', [DataQualityIssueController::class, 'index'])->name('index');
                    Route::get('/{issue}', [DataQualityIssueController::class, 'show'])->name('show');
                    Route::post('/{issue}/review', [DataQualityIssueController::class, 'review'])->name('review');
                    Route::post('/{issue}/confirm', [DataQualityIssueController::class, 'confirm'])->name('confirm');
                    Route::post('/{issue}/dismiss', [DataQualityIssueController::class, 'dismiss'])->name('dismiss');
                    Route::post('/{issue}/resolve', [DataQualityIssueController::class, 'resolve'])->name('resolve');
                });

                Route::prefix('scans')->name('scans.')->group(function () {
                    Route::get('/', [DataQualityScanController::class, 'index'])->name('index');
                    Route::post('/', [DataQualityScanController::class, 'store'])->name('store');
                    Route::get('/{scan}', [DataQualityScanController::class, 'show'])->name('show');
                });
            });
    });
});
