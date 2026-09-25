<?php

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\DepartmentEscalation;
use App\Models\DepartmentTarget;
use App\Models\Employee;
use App\Models\OcrProcessing;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminExecutiveDashboardService
{
    public function getDashboard(): array
    {
        $employees = Employee::with(['department:id,name', 'position:id,name'])->get();
        $departments = Department::all();
        $assignments = TaskAssignment::with(['task:id,title,due_date,department_id', 'employee:id,first_name,last_name,department_id,employee_number'])->get();
        $reports = Report::with(['task:id,title', 'employee:id,first_name,last_name,department_id'])->get();
        $ocrRecords = OcrProcessing::all();
        $escalations = DepartmentEscalation::where('status', 'open')->get();

        $totalEmployees = $employees->count();
        $activeEmployees = $employees->where('employment_status', 'active')->count();
        $totalDepts = $departments->count();

        $totalAssignments = $assignments->count();
        $completedAssignments = $assignments->whereIn('status', ['completed', 'approved'])->count();
        $pendingAssignments = $assignments->whereIn('status', ['assigned', 'in_progress'])->count();

        $now = Carbon::now();
        $overdueAssignments = $assignments->filter(function ($a) use ($now) {
            $deadline = $a->due_date ?? $a->task?->due_date;
            return $deadline && Carbon::parse($deadline)->isPast() && !in_array($a->status, ['completed', 'approved']);
        });

        $submittedReports = $reports->count();
        $pendingReviews = $reports->whereIn('status', ['submitted', 'under_review'])->count();
        $approvedReports = $reports->where('status', 'approved')->count();
        $revisionRequired = $reports->where('status', 'revision_required')->count();
        $rejectedReports = $reports->where('status', 'rejected')->count();

        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 1) : 0;

        $onTimeCount = $assignments->filter(function ($a) {
            if (!$a->completed_at) return false;
            $deadline = $a->due_date ?? $a->task?->due_date;
            return !$deadline || Carbon::parse($a->completed_at)->lte(Carbon::parse($deadline));
        })->count();
        $onTimeRate = $completedAssignments > 0 ? round(($onTimeCount / $completedAssignments) * 100, 1) : 100;

        $reviewedReports = $approvedReports + $revisionRequired + $rejectedReports;
        $approvalRate = $reviewedReports > 0 ? round(($approvedReports / $reviewedReports) * 100, 1) : 0;

        // Institution Progress & Targets
        $targets = DepartmentTarget::all();
        $targetProgress = $completionRate;

        // Department Performance Breakdown
        $deptPerformance = $departments->map(function ($d) use ($employees, $assignments, $reports) {
            $deptEmployees = $employees->where('department_id', $d->id);
            $empIds = $deptEmployees->pluck('id');
            $deptAssignments = $assignments->whereIn('employee_id', $empIds);
            $total = $deptAssignments->count();
            $completed = $deptAssignments->whereIn('status', ['completed', 'approved'])->count();
            $overdue = $deptAssignments->filter(function ($a) {
                $deadline = $a->due_date ?? $a->task?->due_date;
                return $deadline && Carbon::parse($deadline)->isPast() && !in_array($a->status, ['completed', 'approved']);
            })->count();
            $deptReports = $reports->whereIn('employee_id', $empIds)->count();

            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            return [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'employees_count' => $deptEmployees->count(),
                'tasks_count' => $total,
                'completed_count' => $completed,
                'overdue_count' => $overdue,
                'reports_count' => $deptReports,
                'completion_rate' => $rate,
            ];
        })->sortByDesc('completion_rate')->values()->map(function ($item, $idx) {
            $item['rank'] = $idx + 1;
            return $item;
        })->toArray();

        // Top Monthly Achievers across institution
        $topAchievers = $employees->map(function ($emp) use ($assignments) {
            $empAssignments = $assignments->where('employee_id', $emp->id);
            $total = $empAssignments->count();
            $completed = $empAssignments->whereIn('status', ['completed', 'approved'])->count();
            $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

            return [
                'employee_id' => $emp->id,
                'name' => $emp->first_name . ' ' . $emp->last_name,
                'department_name' => $emp->department?->name ?? 'Academic',
                'position' => $emp->position?->name ?? 'Faculty',
                'completed_tasks' => $completed,
                'completion_rate' => $rate,
                'badge' => $rate >= 90 ? 'Outstanding Achievement' : ($rate >= 75 ? 'High Performer' : 'Target Achiever'),
                'icon' => $rate >= 90 ? '🏆' : ($rate >= 75 ? '⭐' : '🎯'),
            ];
        })->sortByDesc('completion_rate')->values()->take(5)->toArray();

        // Critical Alerts
        $topOverdue = $overdueAssignments->map(function ($a) use ($now, $departments) {
            $deadline = $a->due_date ?? $a->task?->due_date;
            $dept = $departments->firstWhere('id', $a->task?->department_id);
            return [
                'assignment_id' => $a->id,
                'task_title' => $a->task?->title ?? 'Untitled Task',
                'employee_name' => $a->employee ? $a->employee->first_name . ' ' . $a->employee->last_name : 'Faculty',
                'department_name' => $dept?->name ?? 'Academic',
                'days_overdue' => $deadline ? max(1, (int) Carbon::parse($deadline)->diffInDays($now)) : 0,
            ];
        })->values()->take(5)->toArray();

        $ocrFailedCount = $ocrRecords->where('status', 'failed')->count();

        // AI Executive Summary
        $aiSummary = "John Paul College has achieved an overall task completion rate of {$completionRate}% across {$totalDepts} academic departments. Top performing department is " . ($deptPerformance[0]['name'] ?? 'Education') . " with {$deptPerformance[0]['completion_rate']}% completion. On-time submission punctuality stands at {$onTimeRate}%.";

        $accomplishments = [
            "{$completedAssignments} out of {$totalAssignments} institutional deliverables completed.",
            "{$approvedReports} faculty reports reviewed and formally approved.",
            "Overall on-time submission rate reached {$onTimeRate}%.",
        ];

        $areasNeedingAttention = [];
        if ($overdueAssignments->count() > 0) {
            $areasNeedingAttention[] = "{$overdueAssignments->count()} deliverables are currently past deadline across departments.";
        }
        if ($revisionRequired > 0) {
            $areasNeedingAttention[] = "{$revisionRequired} reports returned for revision require follow-up.";
        }
        if ($escalations->count() > 0) {
            $areasNeedingAttention[] = "{$escalations->count()} active issue escalations from Department Heads await administrative review.";
        }
        if ($ocrFailedCount > 0) {
            $areasNeedingAttention[] = "{$ocrFailedCount} document(s) encountered OCR errors and require retry.";
        }
        if (empty($areasNeedingAttention)) {
            $areasNeedingAttention[] = "All departmental deliverables and verification pipelines are operating normally.";
        }

        $recommendations = [
            "Encourage Department Heads to conduct milestone reviews for tasks nearing upcoming deadlines.",
            "Review outstanding escalations to prevent potential accreditation and curriculum bottlenecks.",
        ];

        return [
            'institution' => [
                'name' => 'John Paul College',
                'system_name' => 'JPC Portal',
                'academic_year' => '2026-2027',
                'current_term' => 'First Semester 2026',
            ],
            'kpis' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'total_departments' => $totalDepts,
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
                'institution_progress' => $completionRate,
            ],
            'status_distribution' => [
                'tasks' => [
                    'completed' => $completedAssignments,
                    'in_progress' => $assignments->where('status', 'in_progress')->count(),
                    'pending' => $assignments->where('status', 'assigned')->count(),
                    'overdue' => $overdueAssignments->count(),
                ],
                'reports' => [
                    'approved' => $approvedReports,
                    'under_review' => $pendingReviews,
                    'revision_required' => $revisionRequired,
                    'rejected' => $rejectedReports,
                    'submitted' => $submittedReports,
                ],
            ],
            'department_performance' => $deptPerformance,
            'monthly_achievements' => $topAchievers,
            'critical_alerts' => [
                'overdue_tasks' => $topOverdue,
                'pending_reviews_count' => $pendingReviews,
                'revision_count' => $revisionRequired,
                'open_escalations_count' => $escalations->count(),
                'ocr_failed_count' => $ocrFailedCount,
            ],
            'ai_executive_summary' => [
                'text' => $aiSummary,
                'key_accomplishments' => $accomplishments,
                'areas_needing_attention' => $areasNeedingAttention,
                'recommendations' => $recommendations,
                'disclaimer' => 'AI-generated summary based on recorded system data. Verify against detailed reports.',
            ],
        ];
    }
}