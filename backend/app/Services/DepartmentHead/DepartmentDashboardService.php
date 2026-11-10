<?php

namespace App\Services\DepartmentHead;

use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\Report;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;

class DepartmentDashboardService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context
    ) {}

    public function getDashboard(User $user, ?int $explicitDeptId = null): array
    {
        $department = $this->context->resolveDepartment($user, $explicitDeptId);
        $employeeIds = Employee::where('department_id', $department->id)->pluck('id');

        $employees = Employee::where('department_id', $department->id)
            ->with(['position:id,name', 'user:id,email,status'])
            ->get();

        $assignments = TaskAssignment::whereIn('employee_id', $employeeIds)
            ->with(['task:id,title,priority,due_date,status', 'employee:id,first_name,last_name,employee_number'])
            ->get();

        $reports = Report::whereIn('employee_id', $employeeIds)
            ->with(['task:id,title', 'employee:id,first_name,last_name'])
            ->get();

        $totalEmployees = $employees->count();
        $activeEmployees = $employees->where('employment_status', 'active')->count();

        $totalAssignments = $assignments->count();
        $completedAssignments = $assignments->whereIn('status', ['completed', 'approved'])->count();
        $pendingAssignments = $assignments->whereIn('status', ['assigned', 'in_progress'])->count();

        $now = Carbon::now();
        $overdueAssignments = $assignments->filter(function ($a) {
            $deadline = $a->due_date ?? $a->task?->due_date;

            return $deadline && Carbon::parse($deadline)->isPast() && ! in_array($a->status, ['completed', 'approved']);
        });

        $submittedReports = $reports->count();
        $pendingReviews = $reports->whereIn('status', ['submitted', 'under_review'])->count();
        $approvedReports = $reports->where('status', 'approved')->count();
        $revisionRequired = $reports->where('status', 'revision_required')->count();

        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0;

        $onTimeCount = $assignments->filter(function ($a) {
            if (! $a->completed_at) {
                return false;
            }
            $deadline = $a->due_date ?? $a->task?->due_date;

            return ! $deadline || Carbon::parse($a->completed_at)->lte(Carbon::parse($deadline));
        })->count();
        $onTimeRate = $completedAssignments > 0 ? round(($onTimeCount / $completedAssignments) * 100, 1) : 100;

        $reviewedReports = $approvedReports + $revisionRequired + $reports->where('status', 'rejected')->count();
        $approvalRate = $reviewedReports > 0 ? round(($approvedReports / $reviewedReports) * 100, 1) : 0;

        // Target
        $target = DepartmentTarget::where('department_id', $department->id)
            ->orderBy('id', 'desc')
            ->first();

        $targetData = null;
        if ($target) {
            $targetVal = max(1, (int) ($target->target_value ?? 10));
            $targetCompleted = min($targetVal, $completedAssignments);
            $targetProgress = round(($targetCompleted / $targetVal) * 100, 1);
            $targetData = [
                'id' => $target->id,
                'name' => $target->target_name ?? 'Department Target',
                'target' => $targetVal,
                'completed' => $targetCompleted,
                'remaining' => max(0, $targetVal - $targetCompleted),
                'progress_percent' => $targetProgress,
                'is_at_risk' => $targetProgress < 50 && Carbon::now()->month >= 6,
            ];
        }

        // Overdue Alerts
        $overdueAlerts = $overdueAssignments->map(function ($a) use ($now) {
            $deadline = $a->due_date ?? $a->task?->due_date;
            $diff = $deadline ? Carbon::parse($deadline)->diffInDays($now) : 0;

            return [
                'assignment_id' => $a->id,
                'task_id' => $a->task_id,
                'task_title' => $a->task?->title ?? 'Untitled Task',
                'employee_name' => $a->employee ? $a->employee->first_name.' '.$a->employee->last_name : 'Unassigned',
                'deadline' => $deadline ? Carbon::parse($deadline)->format('Y-m-d') : null,
                'days_overdue' => max(1, (int) $diff),
            ];
        })->values()->take(5);

        // Pending Reviews
        $pendingReviewAlerts = $reports->whereIn('status', ['submitted', 'under_review'])
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'title' => $r->title,
                    'task_title' => $r->task?->title ?? '—',
                    'employee_name' => $r->employee ? $r->employee->first_name.' '.$r->employee->last_name : '—',
                    'submitted_at' => $r->submitted_at ?? $r->created_at?->toIso8601String(),
                    'ai_status' => $r->ai_status ?? 'completed',
                    'status' => $r->status,
                ];
            })->values()->take(5);

        // Revision Alerts
        $revisionAlerts = $reports->where('status', 'revision_required')
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'title' => $r->title,
                    'task_title' => $r->task?->title ?? '—',
                    'employee_name' => $r->employee ? $r->employee->first_name.' '.$r->employee->last_name : '—',
                    'updated_at' => $r->updated_at?->toIso8601String(),
                ];
            })->values()->take(5);

        // Employee performance breakdown
        $employeeSummary = $employees->map(function ($emp) use ($assignments, $reports) {
            $empAssignments = $assignments->where('employee_id', $emp->id);
            $total = $empAssignments->count();
            $completed = $empAssignments->whereIn('status', ['completed', 'approved'])->count();
            $pending = $empAssignments->whereIn('status', ['assigned', 'in_progress'])->count();
            $overdue = $empAssignments->filter(function ($a) {
                $d = $a->due_date ?? $a->task?->due_date;

                return $d && Carbon::parse($d)->isPast() && ! in_array($a->status, ['completed', 'approved']);
            })->count();
            $empReports = $reports->where('employee_id', $emp->id)->count();

            return [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->first_name.' '.$emp->last_name,
                'position' => $emp->position?->name ?? 'Faculty',
                'status' => $emp->employment_status,
                'tasks_total' => $total,
                'tasks_completed' => $completed,
                'tasks_pending' => $pending,
                'tasks_overdue' => $overdue,
                'reports_count' => $empReports,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        })->values();

        // AI performance summary
        $aiSummary = "Department task completion rate is currently at {$completionRate}% with {$completedAssignments} out of {$totalAssignments} tasks completed. On-time delivery rate is {$onTimeRate}%.";
        if ($pendingReviews > 0) {
            $aiSummary .= " There are {$pendingReviews} reports awaiting review.";
        }
        if ($overdueAssignments->count() > 0) {
            $aiSummary .= " Notice: {$overdueAssignments->count()} task assignments are past deadline and require follow-up.";
        }

        return [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'academic_year' => '2026-2027',
                'department_head' => $user->name,
            ],
            'kpis' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'total_tasks' => $totalAssignments,
                'completed_tasks' => $completedAssignments,
                'pending_tasks' => $pendingAssignments,
                'overdue_tasks' => $overdueAssignments->count(),
                'submitted_reports' => $submittedReports,
                'pending_reviews' => $pendingReviews,
                'approved_reports' => $approvedReports,
                'revision_required' => $revisionRequired,
                'completion_rate' => $completionRate,
                'on_time_rate' => $onTimeRate,
                'approval_rate' => $approvalRate,
            ],
            'target' => $targetData,
            'overdue_alerts' => $overdueAlerts,
            'pending_review_alerts' => $pendingReviewAlerts,
            'revision_alerts' => $revisionAlerts,
            'employee_summary' => $employeeSummary,
            'ai_summary' => [
                'text' => $aiSummary,
                'disclaimer' => 'AI-generated summary based on recorded system data.',
            ],
        ];
    }
}
