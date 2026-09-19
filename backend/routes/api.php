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
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TaskAssignmentController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskProgressController;
use App\Http\Controllers\Api\V1\TaskReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\AdminAnnouncementController;
use App\Http\Controllers\Api\V1\Messages\MessageController;
use App\Http\Controllers\Api\V1\Notifications\AnnouncementController;
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
                Route::get('/{report}/processing-status', [AiReportController::class, 'processingStatus'])
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
        });

                // Employee Portal (Phase 3.3-V) â€” self-service surface keyed off the
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
    });
});


