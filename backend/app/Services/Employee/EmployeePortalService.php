<?php

namespace App\Services\Employee;

use App\Models\Employee;
use App\Models\Report;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Reports\PerformanceCalculationService;
use App\Services\TaskManagement\TaskAssignmentService;
use App\Services\AiReports\ReportAnalysisService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Employee self-service portal aggregation (Phase 3.3-V).
 *
 * Exposes a single, cache-backed source of truth for the employee-facing
 * surfaces: dashboard KPIs, personal tasks, reports, performance, achievements,
 * targets, calendar, and notifications.
 *
 * SECURITY: Every method resolves the employee from the authenticated User
 * relationship. No employee_id is ever accepted from request input (IDOR-safe).
 *
 * AI ADVISORY: AI results are surfaced with disclaimers and are never the
 * source of truth for performance scores or disciplinary actions.
 */
class EmployeePortalService
{
    public function __construct(
        private readonly TaskAssignmentService $taskAssignmentService,
        private readonly PerformanceCalculationService $performanceCalculation,
        private readonly NotificationService $notificationService,
        private readonly ReportAnalysisService $reportAnalysisService,
    ) {}

    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_KEY_PREFIX = 'employee:portal:';

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboard(User $user): array
    {
        $employee = $this->requireEmployee($user);

        return Cache::remember($this->key($employee, 'dashboard'), self::CACHE_TTL, function () use ($employee) {
            $assignments = $this->assignments($employee);

            $taskKpis   = $this->taskKpis($assignments);
            $reportKpis = $this->reportKpis($employee);
            $onTimeRate = $this->onTimeRate($assignments);
            $deadlines  = $this->upcomingDeadlines($employee, $assignments);
            $overdue    = $this->overdueTasks($employee, $assignments);

            return [
                'employee' => [
                    'id'          => $employee->id,
                    'name'        => $employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name),
                    'employee_no' => $employee->employee_no ?? $employee->employee_number ?? null,
                    'department'  => $employee->department?->name,
                    'position'    => $employee->position?->name ?? $employee->position_title ?? null,
                ],
                'kpis'           => array_merge($taskKpis, $reportKpis, ['on_time_rate' => $onTimeRate]),
                'deadlines'      => $deadlines->values()->toArray(),
                'overdue'        => $overdue->values()->toArray(),
                'completion_rate' => $taskKpis['completion_rate'],
                'recent_reports' => $this->recentReports($employee),
                'recent_achievements' => $this->recentBadges($employee),
                'recent_notifications' => $this->recentNotifications($employee->user ?? $employee->user()->first()),
                'annual_target'  => $this->annualTarget($employee),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tasks
    // ─────────────────────────────────────────────────────────────────────────

    public function tasks(User $user, array $filters = []): array
    {
        $employee    = $this->requireEmployee($user);
        $query       = TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->with(['task', 'progress'])
            ->latest('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $query->whereHas('task', fn ($q) => $q->where('title', 'like', '%' . $filters['search'] . '%'));
        }

        $assignments = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'tasks'   => collect($assignments->items())->map(fn (TaskAssignment $a) => $this->taskShape($a))->values(),
            'meta'    => [
                'total'        => $assignments->total(),
                'per_page'     => $assignments->perPage(),
                'current_page' => $assignments->currentPage(),
                'last_page'    => $assignments->lastPage(),
            ],
            'summary' => $this->taskKpis($this->assignments($employee)),
        ];
    }

    public function task(User $user, int $taskAssignmentId): array
    {
        $employee   = $this->requireEmployee($user);
        $assignment = TaskAssignment::with(['task', 'progress', 'reviews'])
            ->where('employee_id', $employee->id) // IDOR guard
            ->findOrFail($taskAssignmentId);

        return $this->taskShape($assignment, detailed: true);
    }

    public function updateTaskProgress(User $user, int $assignmentId, int $progress): array
    {
        $employee   = $this->requireEmployee($user);
        $assignment = TaskAssignment::where('employee_id', $employee->id)
            ->findOrFail($assignmentId);

        if ($progress < 0 || $progress > 100) {
            throw ValidationException::withMessages(['progress' => ['Progress must be between 0 and 100.']]);
        }

        $assignment->update(['completion_percentage' => $progress]);

        // If 100%, auto-update to in_progress (completion still requires explicit submit)
        if ($progress === 100 && $assignment->status === 'pending') {
            $assignment->update(['status' => 'in_progress']);
        }

        $this->invalidate($employee);

        return $this->taskShape($assignment->fresh(['task']));
    }

    public function submitTask(User $user, int $assignmentId, array $data): array
    {
        $employee   = $this->requireEmployee($user);
        $assignment = TaskAssignment::where('employee_id', $employee->id)
            ->with('task')
            ->findOrFail($assignmentId);

        // Deadline check (server-side, never trusted from client)
        $isLate = false;
        if ($assignment->due_at && Carbon::now()->isAfter($assignment->due_at)) {
            $isLate = true;
        }

        $assignment->update([
            'status'               => 'submitted',
            'completion_percentage' => 100,
            'submitted_at'         => Carbon::now(),
            'completion_notes'     => $data['notes'] ?? null,
            'is_late'              => $isLate,
            'late_reason'          => $isLate ? ($data['late_reason'] ?? null) : null,
        ]);

        $this->invalidate($employee);

        return array_merge($this->taskShape($assignment->fresh()), ['is_late' => $isLate]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reports
    // ─────────────────────────────────────────────────────────────────────────

    public function reports(User $user, array $filters = []): array
    {
        $employee = $this->requireEmployee($user);
        $query    = Report::where('employee_id', $employee->id)
            ->with(['task'])
            ->orderByDesc('updated_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        $reports = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'reports' => collect($reports->items())->map(fn (Report $r) => $this->reportListShape($r))->values(),
            'meta'    => [
                'total'        => $reports->total(),
                'per_page'     => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'last_page'    => $reports->lastPage(),
            ],
        ];
    }

    public function report(User $user, int $reportId): array
    {
        $employee = $this->requireEmployee($user);
        $report   = Report::where('employee_id', $employee->id) // IDOR guard
            ->with([
                'task',
                'files',
                'versions',
                'aiAnalyses',
                'aiSummaries',
                'aiValidationResults.items',
                'aiSimilarityResults',
                'reviews',
            ])
            ->findOrFail($reportId);

        return $this->reportDetailShape($report);
    }

    public function createReport(User $user, array $data): Report
    {
        $employee = $this->requireEmployee($user);

        // Verify task ownership
        $assignmentExists = TaskAssignment::where('employee_id', $employee->id)
            ->where('task_id', $data['task_id'])
            ->exists();

        if (! $assignmentExists) {
            throw ValidationException::withMessages(['task_id' => ['You are not assigned to this task.']]);
        }

        $report = Report::create([
            'task_id'     => $data['task_id'],
            'employee_id' => $employee->id,
            'title'       => $data['title'],
            'description' => $this->sanitizeHtml($data['content'] ?? ''),
            'remarks'     => $data['references'] ?? null,
            'status'      => 'draft',
            'version'     => 1,
        ]);

        $this->invalidate($employee);

        return $report;
    }

    public function updateReport(User $user, int $reportId, array $data): Report
    {
        $employee = $this->requireEmployee($user);
        $report   = Report::where('employee_id', $employee->id)
            ->findOrFail($reportId);

        if ($report->status !== 'draft') {
            throw ValidationException::withMessages([
                'report' => ['Only draft reports can be edited. Create a revision to make changes.'],
            ]);
        }

        $report->update(array_filter([
            'title'       => $data['title'] ?? $report->title,
            'description' => isset($data['content']) ? $this->sanitizeHtml($data['content']) : $report->description,
            'remarks'     => $data['references'] ?? $report->remarks,
        ]));

        return $report->fresh();
    }

    public function submitReport(User $user, int $reportId): Report
    {
        $employee = $this->requireEmployee($user);
        $report   = Report::where('employee_id', $employee->id)
            ->findOrFail($reportId);

        if ($report->status !== 'draft') {
            throw ValidationException::withMessages([
                'report' => ['Only draft reports can be submitted.'],
            ]);
        }

        $isLate = $report->deadline && Carbon::now()->isAfter(Carbon::parse($report->deadline));

        $report->update([
            'status'       => 'submitted',
            'submitted_at' => Carbon::now(),
            'is_late'      => $isLate,
        ]);

        // Dispatch AI processing jobs if available
        $this->dispatchAiPipeline($report);

        $this->invalidate($employee);

        return $report->fresh();
    }

    public function createRevision(User $user, int $reportId, array $data): Report
    {
        $employee = $this->requireEmployee($user);
        $original = Report::where('employee_id', $employee->id)
            ->findOrFail($reportId);

        if ($original->status !== 'revision_required') {
            throw ValidationException::withMessages([
                'report' => ['A revision can only be created when revision is required by the reviewer.'],
            ]);
        }

        // Snapshot the original version
        $original->versions()->create([
            'version'      => $original->version,
            'content'      => $original->description,
            'status'       => $original->status,
            'submitted_at' => $original->submitted_at,
        ]);

        // Create new draft version
        $newVersion = $original->version + 1;
        $original->update([
            'status'      => 'draft',
            'version'     => $newVersion,
            'description' => $this->sanitizeHtml($data['content'] ?? $original->description),
            'title'       => $data['title'] ?? $original->title,
            'remarks'     => $data['revision_notes'] ?? null,
            'submitted_at' => null,
        ]);

        $this->invalidate($employee);

        return $original->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calendar
    // ─────────────────────────────────────────────────────────────────────────

    public function calendar(User $user): array
    {
        $employee = $this->requireEmployee($user);
        $assignments = $this->assignments($employee);

        $taskEvents = $assignments
            ->filter(fn (TaskAssignment $a) => $a->due_at !== null)
            ->map(fn (TaskAssignment $a) => [
                'id'     => $a->id,
                'type'   => 'task',
                'title'  => $a->task?->title ?? "Task #{$a->task_id}",
                'date'   => $a->due_at->toDateString(),
                'status' => $a->status,
                'is_overdue' => $a->isOverdue(),
                'link'   => "/employee/tasks/{$a->task_id}",
            ]);

        $reportEvents = Report::where('employee_id', $employee->id)
            ->whereNotNull('deadline')
            ->get()
            ->map(fn (Report $r) => [
                'id'     => $r->id,
                'type'   => 'report',
                'title'  => $r->title,
                'date'   => optional($r->deadline)->toDateString(),
                'status' => $r->status,
                'link'   => "/employee/reports/{$r->id}",
            ]);

        return [
            'events' => $taskEvents->merge($reportEvents)->sortBy('date')->values()->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Performance
    // ─────────────────────────────────────────────────────────────────────────

    public function performance(User $user): array
    {
        $employee = $this->requireEmployee($user);

        try {
            $score = $this->performanceCalculation->calculate(authEmployee: $employee);
        } catch (\Throwable) {
            $score = null;
        }

        $assignments = $this->assignments($employee);

        return [
            'performance'  => $score,
            'completion_rate' => $this->taskKpis($assignments)['completion_rate'],
            'on_time_rate' => $this->onTimeRate($assignments),
            'approval_rate' => $this->reportKpis($employee)['approval_rate'],
            'disclaimer'   => 'Performance metrics are calculated from recorded system data. AI-generated summaries are advisory only.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Targets
    // ─────────────────────────────────────────────────────────────────────────

    public function targets(User $user): array
    {
        $employee = $this->requireEmployee($user);

        $targets = $employee->employeeTargets()->get()->map(fn ($target) => [
            'id'           => $target->id,
            'description'  => $target->description,
            'metric'       => $target->metric,
            'target_value' => (float) $target->target_value,
            'actual_value' => (float) ($target->actual_value ?? 0),
            'progress'     => $target->target_value > 0
                ? round(((float) ($target->actual_value ?? 0) / (float) $target->target_value) * 100, 2)
                : 0,
            'status'       => $target->target_value > 0 && (float) ($target->actual_value ?? 0) >= (float) $target->target_value
                ? 'achieved'
                : 'in_progress',
            'start_date'   => optional($target->start_date)->toDateString(),
            'end_date'     => optional($target->end_date)->toDateString(),
        ])->values();

        return [
            'targets'      => $targets,
            'annual'       => $this->annualTarget($employee),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Achievements
    // ─────────────────────────────────────────────────────────────────────────

    public function achievements(User $user): array
    {
        $employee = $this->requireEmployee($user);

        $badges = method_exists($employee, 'badges')
            ? $employee->badges()->get()->map(fn ($badge) => [
                'id'          => $badge->id,
                'name'        => $badge->name,
                'description' => $badge->description,
                'icon'        => $badge->icon ?? null,
                'criteria'    => $badge->criteria ?? null,
                'earned_at'   => optional($badge->pivot?->created_at ?? $badge->awarded_at)->toIso8601String(),
            ])->values()
            : collect();

        $monthly = method_exists($employee, 'monthlyAchievements')
            ? $employee->monthlyAchievements()->orderByDesc('month')->get()->map(fn ($m) => [
                'month'       => $m->month,
                'month_name'  => Carbon::create(null, $m->month)->format('F'),
                'completion'  => $m->completion_rate,
                'on_time'     => $m->on_time_rate,
                'rank'        => $m->rank ?? null,
                'badge'       => $m->badge ?? null,
                'achievement' => $m->achievement ?? null,
            ])->values()
            : collect();

        return [
            'badges'  => $badges,
            'monthly' => $monthly,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Notifications
    // ─────────────────────────────────────────────────────────────────────────

    public function notifications(User $user): array
    {
        $items = $this->notificationService->forUser($user->id)
            ->take(20)
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'message'    => $n->message,
                'is_read'    => $n->read_at !== null,
                'read_at'    => optional($n->read_at)->toIso8601String(),
                'created_at' => optional($n->created_at)->toIso8601String(),
            ])
            ->values();

        return ['notifications' => $items, 'total' => $items->count()];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Profile
    // ─────────────────────────────────────────────────────────────────────────

    public function profile(User $user): array
    {
        $employee = $this->requireEmployee($user);

        return [
            'name'          => $employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name),
            'email'         => $user->email,
            'employee_no'   => $employee->employee_no ?? $employee->employee_number ?? null,
            'department'    => $employee->department?->name,
            'position'      => $employee->position?->name ?? $employee->position_title ?? null,
            'employee_type' => $employee->employee_type ?? null,
            'status'        => $employee->status ?? null,
        ];
    }

    public function updateProfile(User $user, array $data): array
    {
        // Only allow updating email (and other permitted fields)
        if (isset($data['email'])) {
            $user->update(['email' => $data['email']]);
        }

        $this->invalidate($this->requireEmployee($user));

        return $this->profile($user);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($newPassword)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cache invalidation
    // ─────────────────────────────────────────────────────────────────────────

    public function invalidate(Employee $employee): void
    {
        foreach (['dashboard', 'calendar', 'performance', 'targets', 'achievements'] as $segment) {
            Cache::forget($this->key($employee, $segment));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function requireEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if ($employee === null) {
            throw new \App\Exceptions\Domain\NotFoundException('No employee profile linked to this account.');
        }

        return $employee;
    }

    private function assignments(Employee $employee): Collection
    {
        return TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->with(['task', 'progress'])
            ->get();
    }

    private function taskKpis(Collection $assignments): array
    {
        $total     = max($assignments->count(), 1);
        $completed = $assignments->whereIn('status', ['completed', 'approved'])->count();
        $overdue   = $assignments->filter(fn (TaskAssignment $a) => $a->isOverdue())->count();

        return [
            'total_tasks'     => $assignments->count(),
            'completed_tasks' => $completed,
            'pending_tasks'   => $assignments->where('status', 'pending')->count(),
            'in_progress_tasks' => $assignments->where('status', 'in_progress')->count(),
            'overdue_tasks'   => $overdue,
            'completion_rate' => round(($completed / $total) * 100, 2),
        ];
    }

    private function reportKpis(Employee $employee): array
    {
        $reports  = $this->employeeReports($employee);
        $total    = max($reports->count(), 1);
        $approved = $reports->where('status', 'approved')->count();

        return [
            'total_reports'     => $reports->count(),
            'submitted_reports' => $reports->whereIn('status', ['submitted', 'under_review', 'revision_required', 'approved'])->count(),
            'approved_reports'  => $approved,
            'revision_required' => $reports->where('status', 'revision_required')->count(),
            'approval_rate'     => round(($approved / $total) * 100, 2),
        ];
    }

    private function onTimeRate(Collection $assignments): float
    {
        $finished = $assignments->whereIn('status', ['completed', 'approved']);
        if ($finished->isEmpty()) {
            return 0.0;
        }
        $onTime = $finished->filter(
            fn (TaskAssignment $a) => $a->completed_at && $a->due_at && $a->completed_at->lte($a->due_at)
        )->count();

        return round(($onTime / $finished->count()) * 100, 2);
    }

    private function upcomingDeadlines(Employee $employee, Collection $assignments): Collection
    {
        return $assignments
            ->filter(fn (TaskAssignment $a) =>
                $a->due_at &&
                ! $a->isOverdue() &&
                ! in_array($a->status, ['completed', 'approved'], true) &&
                $a->due_at->isAfter(Carbon::now()) &&
                $a->due_at->isBefore(Carbon::now()->addDays(14))
            )
            ->sortBy('due_at')
            ->take(5)
            ->map(fn (TaskAssignment $a) => [
                'id'             => $a->id,
                'task_id'        => $a->task_id,
                'title'          => $a->task?->title,
                'deadline'       => $a->due_at->toIso8601String(),
                'days_remaining' => max(0, (int) Carbon::now()->startOfDay()->diffInDays($a->due_at->startOfDay(), false)),
                'status'         => $a->status,
            ]);
    }

    private function overdueTasks(Employee $employee, Collection $assignments): Collection
    {
        return $assignments
            ->filter(fn (TaskAssignment $a) =>
                $a->isOverdue() &&
                ! in_array($a->status, ['completed', 'approved'], true)
            )
            ->sortBy('due_at')
            ->map(fn (TaskAssignment $a) => [
                'id'          => $a->id,
                'task_id'     => $a->task_id,
                'title'       => $a->task?->title,
                'deadline'    => $a->due_at?->toIso8601String(),
                'days_overdue' => (int) abs(Carbon::now()->startOfDay()->diffInDays($a->due_at?->startOfDay() ?? Carbon::now(), false)),
                'status'      => $a->status,
            ]);
    }

    private function annualTarget(Employee $employee): array
    {
        $year = Carbon::now()->year;
        $targets = method_exists($employee, 'employeeTargets')
            ? $employee->employeeTargets()->get()
            : collect();

        $totalTarget = $targets->sum(fn ($t) => (float) ($t->target_value ?? 0));
        $totalActual = $targets->sum(fn ($t) => (float) ($t->actual_value ?? 0));
        $progress    = $totalTarget > 0 ? round(($totalActual / $totalTarget) * 100, 2) : 0;

        return [
            'year'             => $year,
            'target'           => $totalTarget,
            'completed'        => $totalActual,
            'progress_percent' => min(100, $progress),
            'label'            => "Annual Target {$year}",
        ];
    }

    private function recentReports(Employee $employee): array
    {
        return $this->employeeReports($employee)
            ->take(5)
            ->map(fn (Report $r) => $this->reportListShape($r))
            ->values()
            ->toArray();
    }

    private function recentBadges(Employee $employee): array
    {
        if (! method_exists($employee, 'badges')) {
            return [];
        }

        return $employee->badges()->latest('pivot_created_at')->take(3)->get()
            ->map(fn ($b) => [
                'id'        => $b->id,
                'name'      => $b->name,
                'icon'      => $b->icon ?? null,
                'earned_at' => optional($b->pivot?->created_at)->toIso8601String(),
            ])
            ->values()
            ->toArray();
    }

    private function recentNotifications(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $this->notificationService->forUser($user->id)
            ->take(5)
            ->map(fn ($n) => [
                'id'      => $n->id,
                'title'   => $n->title,
                'message' => $n->message,
                'is_read' => $n->read_at !== null,
                'created_at' => optional($n->created_at)->toIso8601String(),
            ])
            ->values()
            ->toArray();
    }

    private function employeeReports(Employee $employee): Collection
    {
        return $employee->reports()->orderByDesc('created_at')->get();
    }

    private function taskShape(TaskAssignment $a, bool $detailed = false): array
    {
        $base = [
            'id'            => $a->id,
            'task_id'       => $a->task_id,
            'title'         => $a->task?->title,
            'description'   => $detailed ? ($a->task?->description) : null,
            'priority'      => $a->task?->priority,
            'assigned_date' => optional($a->assigned_at)->toIso8601String(),
            'deadline'      => optional($a->due_at)->toIso8601String(),
            'progress'      => (int) ($a->completion_percentage ?? 0),
            'status'        => $a->status,
            'is_overdue'    => $a->isOverdue(),
            'is_late'       => (bool) ($a->is_late ?? false),
            'submitted_at'  => optional($a->submitted_at)->toIso8601String(),
        ];

        if ($detailed) {
            $base['requirements']  = $a->task?->requirements ?? null;
            $base['instructions']  = $a->task?->instructions ?? null;
            $base['target']        = $a->task?->target ?? null;
            $base['notes']         = $a->completion_notes ?? null;
            $base['late_reason']   = $a->late_reason ?? null;
            $base['reviews']       = $a->reviews?->map(fn ($r) => [
                'reviewer'  => $r->reviewer?->name,
                'decision'  => $r->decision,
                'comments'  => $r->comments,
                'reviewed_at' => optional($r->reviewed_at)->toIso8601String(),
            ])->values() ?? [];
        }

        return $base;
    }

    private function reportListShape(Report $r): array
    {
        $latestAnalysis   = $r->aiAnalyses()->latest('id')->first();
        $latestValidation = $r->aiValidationResults()->latest('id')->first();

        return [
            'id'           => $r->id,
            'title'        => $r->title,
            'task_id'      => $r->task_id,
            'task_title'   => $r->task?->title,
            'submitted_at' => optional($r->submitted_at)->toIso8601String(),
            'updated_at'   => optional($r->updated_at)->toIso8601String(),
            'status'       => $r->status,
            'version'      => $r->version ?? 1,
            'is_late'      => (bool) ($r->is_late ?? false),
            'ai_status'    => $latestAnalysis?->status ?? ($r->ai_processing_status ?? 'not_requested'),
            'review_status' => $r->review_status ?? null,
            'is_revision'  => ($r->version ?? 1) > 1,
            'advisory_label' => 'AI-Assisted Validation',
            'disclaimer'   => 'AI analysis is advisory. Final validation requires authorized human review.',
        ];
    }

    private function reportDetailShape(Report $report): array
    {
        $latestAnalysis   = $report->aiAnalyses()->latest('id')->first();
        $latestSummary    = $report->aiSummaries()->latest('id')->first();
        $latestValidation = $report->aiValidationResults()->latest('id')->first();
        $latestSimilarity = $report->aiSimilarityResults()->latest('id')->first();
        $latestOcr        = method_exists($report, 'ocrProcessings')
            ? $report->ocrProcessings()->latest('id')->first()
            : null;

        // OCR section
        $ocr = null;
        if ($latestOcr) {
            $confidence = $latestOcr->confidence ?? null;
            $confLabel  = $confidence === null ? null :
                ($confidence >= 80 ? 'high' : ($confidence >= 50 ? 'medium' : 'low'));
            $ocr = [
                'status'          => $latestOcr->status,
                'extracted_text'  => $latestOcr->extracted_text,
                'confidence'      => $confidence,
                'confidence_label' => $confLabel,
                'warning'         => ($confLabel === 'low') ? 'Some extracted text may require manual verification.' : null,
            ];
        }

        // AI Summary
        $aiSummary = null;
        if ($latestSummary) {
            $raw = is_string($latestSummary->summary) ? json_decode($latestSummary->summary, true) : $latestSummary->summary;
            $aiSummary = [
                'status'            => $latestAnalysis?->status ?? 'completed',
                'executive_summary' => $raw['executive_summary'] ?? ($latestSummary->summary ?? null),
                'key_points'        => $raw['key_points'] ?? [],
                'accomplishments'   => $raw['accomplishments'] ?? [],
                'issues'            => $raw['issues'] ?? [],
                'recommendations'   => $raw['recommendations'] ?? [],
                'disclaimer'        => 'AI-generated summary. Verify against the original report.',
            ];
        }

        // AI Validation
        $aiValidation = null;
        if ($latestValidation) {
            $items   = $latestValidation->items ?? $latestValidation->validationItems ?? collect();
            $reqChecks = collect($items)->where('type', 'requirement')->map(fn ($i) => [
                'requirement' => $i->requirement ?? $i->description ?? '',
                'status'      => $i->status ?? 'needs_review',
                'note'        => $i->note ?? null,
            ]);
            $refResults = collect($items)->where('type', 'reference')->map(fn ($i) => [
                'reference' => $i->reference ?? $i->description ?? '',
                'status'    => $i->status ?? 'needs_review',
                'note'      => $i->note ?? null,
            ]);

            // Similarity — use neutral language (never "plagiarism")
            $simResult = 'no_match';
            $simLabel  = 'No Significant Match';
            if ($latestSimilarity) {
                $score = $latestSimilarity->similarity_score ?? 0;
                if ($score >= 70) { $simResult = 'potential_match'; $simLabel = 'Potential Similarity Detected'; }
                elseif ($score >= 40) { $simResult = 'needs_review'; $simLabel = 'Similarity Needs Review'; }
            }

            $aiValidation = [
                'status'              => $latestValidation->status ?? 'completed',
                'requirement_checks'  => $reqChecks->values(),
                'reference_results'   => $refResults->values(),
                'similarity'          => [
                    'result' => $simResult,
                    'label'  => $simLabel,
                    'note'   => 'Potential similarity detected. This is an automated advisory check — not a final determination.',
                ],
                'flags'               => $latestValidation->flags ?? [],
                'overall_score'       => $latestValidation->overall_score,
                'advisory_label'      => 'AI-Assisted Validation',
                'disclaimer'          => 'AI analysis is advisory. Final validation requires authorized human review.',
            ];
        }

        // Human Review history
        $reviews = $report->reviews()->with('reviewer')->orderBy('created_at')->get()->map(fn ($rv) => [
            'reviewer'  => $rv->reviewer?->name,
            'decision'  => $rv->decision ?? $rv->status ?? null,
            'comments'  => $rv->comments ?? $rv->notes ?? null,
            'date'      => optional($rv->reviewed_at ?? $rv->created_at)->toIso8601String(),
        ]);

        // Version history (read-only previous versions)
        $versions = $report->versions()->orderBy('version')->get()->map(fn ($v) => [
            'version'      => $v->version,
            'status'       => $v->status,
            'submitted_at' => optional($v->submitted_at)->toIso8601String(),
            'created_at'   => optional($v->created_at)->toIso8601String(),
            'is_current'   => false,
        ]);

        return array_merge($this->reportListShape($report), [
            'content'        => $report->description,
            'references'     => $report->remarks,
            'deadline'       => optional($report->deadline)->toDateString(),
            'approved_at'    => optional($report->approved_at)->toIso8601String(),
            'rejected_at'    => optional($report->rejected_at)->toIso8601String(),
            'files'          => $report->files->map(fn ($f) => [
                'id'          => $f->id,
                'file_name'   => $f->file_name ?? $f->original_name ?? 'document',
                'file_size'   => $f->file_size ?? $f->size ?? null,
                'mime_type'   => $f->mime_type ?? null,
                'uploaded_at' => optional($f->created_at)->toIso8601String(),
            ]),
            'ocr'            => $ocr,
            'ai_summary'     => $aiSummary,
            'ai_validation'  => $aiValidation,
            'review_history' => $reviews->values(),
            'versions'       => $versions->values(),
            'version_count'  => $report->versions()->count() + 1,
        ]);
    }

    private function dispatchAiPipeline(Report $report): void
    {
        // Only dispatch if the job classes exist (graceful degradation)
        if (class_exists(\App\Jobs\ProcessReportOcrJob::class)) {
            dispatch(new \App\Jobs\ProcessReportOcrJob($report->id))->onQueue('ocr');
        }
        if (class_exists(\App\Jobs\GenerateReportSummaryJob::class)) {
            dispatch(new \App\Jobs\GenerateReportSummaryJob($report->id))->onQueue('ai');
        }
        if (class_exists(\App\Jobs\ValidateReportJob::class)) {
            dispatch(new \App\Jobs\ValidateReportJob($report->id))->onQueue('ai');
        }
    }

    private function sanitizeHtml(string $content): string
    {
        // Strip all tags except safe formatting — prevents XSS
        return strip_tags($content, '<p><br><strong><em><ul><ol><li><h2><h3><h4><blockquote>');
    }

    private function key(Employee $employee, string $segment): string
    {
        return self::CACHE_KEY_PREFIX . $segment . ':' . $employee->id;
    }
}
