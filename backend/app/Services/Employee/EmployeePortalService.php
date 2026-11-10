<?php

namespace App\Services\Employee;

use App\Enums\ReportStatus;
use App\Jobs\GenerateReportSummaryJob;
use App\Jobs\ProcessReportOcrJob;
use App\Jobs\ValidateReportJob;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportFile;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\AiReports\ReportAnalysisService;
use App\Services\Reports\EmployeeReportSummaryService;
use App\Services\Reports\PerformanceCalculationService;
use App\Services\TaskManagement\TaskAssignmentService;
use App\Services\TaskManagement\TaskProgressService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        private readonly ReportAnalysisService $reportAnalysisService,
        private readonly EmployeeTargetService $employeeTargetService,
        private readonly ?EmployeeReportSummaryService $employeeReportSummaryService = null,
    ) {}

    private const CACHE_TTL = 300; // 5 minutes

    private const CACHE_KEY_PREFIX = 'employee:portal:';

    /**
     * Real task_assignments.status values written by the system. A task that has
     * not been started is "pending"; the legacy "pending" literal is kept so
     * historical rows still count toward the KPI.
     */
    private const TASK_NOT_STARTED = ['assigned', 'accepted', 'pending'];

    private const TASK_IN_PROGRESS = ['in_progress'];

    private const TASK_SUBMITTED = ['submitted'];

    private const TASK_COMPLETED = ['completed', 'approved'];

    /** Statuses that no longer require employee action. */
    private const TASK_CLOSED = ['completed', 'approved', 'cancelled'];

    /** Reports still owned by the employee and therefore actionable. */
    private const REPORT_OPEN = [
        ReportStatus::Draft,
        ReportStatus::Submitted,
        ReportStatus::Processing,
        ReportStatus::UnderReview,
        ReportStatus::RevisionRequired,
        ReportStatus::Resubmitted,
    ];

    /** Reports the employee must actively write or fix. */
    private const REPORT_EMPLOYEE_ACTION = [
        ReportStatus::Draft,
        ReportStatus::RevisionRequired,
    ];

    /** Days ahead used by the "upcoming deadlines" strip. */
    private const DEADLINE_WINDOW_DAYS = 14;

    /** Row caps for the dashboard payload — full lists live on their own pages. */
    private const DASHBOARD_TASK_LIMIT = 5;

    private const DASHBOARD_REPORT_LIMIT = 5;

    private const DASHBOARD_ACTION_LIMIT = 8;

    private const DASHBOARD_SCHEDULE_LIMIT = 6;

    private const DASHBOARD_NOTIFICATION_LIMIT = 5;

    private const DASHBOARD_ACHIEVEMENT_LIMIT = 4;

    private const DASHBOARD_TARGET_LIMIT = 4;

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Single optimized response backing the whole employee workspace. Every
     * section is derived from the authenticated employee only.
     */
    public function dashboard(User $user, ?int $year = null, ?int $month = null): array
    {
        $employee = $this->requireEmployee($user);

        $year = $year ?? Carbon::now()->year;
        $month = $month ?? Carbon::now()->month;

        return Cache::remember(
            $this->key($employee, "v{$this->cacheVersion($employee)}:dashboard:{$year}-{$month}"),
            self::CACHE_TTL,
            function () use ($employee, $year, $month) {
                $assignments = $this->assignments($employee);
                $reports = $this->employeeReports($employee);

                $taskKpis = $this->taskKpis($assignments);
                $reportKpis = $this->reportKpis($reports);
                $onTimeRate = $this->onTimeRate($assignments);

                $deadlines = $this->upcomingDeadlines($assignments, $reports);
                $overdueTasks = $this->overdueTasks($assignments);
                $overdueReports = $this->overdueReports($reports);
                $actionRequired = $this->actionRequired($assignments, $reports);
                $achievements = $this->achievementsPayload($employee);
                $targets = $this->targetsPayload($employee);
                $notifications = $this->recentNotifications($employee->user ?? $employee->user()->first());

                return [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->full_name ?? ($employee->first_name.' '.$employee->last_name),
                        'email' => $employee->user?->email,
                        'employee_no' => $employee->employee_no ?? $employee->employee_number ?? null,
                        'department' => $employee->department?->name,
                        'position' => $employee->position?->name ?? $employee->position_title ?? null,
                    ],
                    'generated_at' => Carbon::now()->toIso8601String(),
                    'kpis' => array_merge($taskKpis, $reportKpis, ['on_time_rate' => $onTimeRate]),
                    'progress' => [
                        'completed' => $taskKpis['completed_tasks'],
                        'total' => $taskKpis['total_tasks'],
                        'percent' => $taskKpis['completion_rate'],
                    ],
                    'tasks' => $this->recentTasks($assignments),
                    'reports' => $this->recentReports($reports),
                    'action_required' => $actionRequired,
                    'deadlines' => $deadlines,
                    'overdue' => $overdueTasks,
                    'overdue_reports' => $overdueReports,
                    'monthly_progress' => $this->monthlyProgress($assignments, $reports, $year, $month),
                    'annual_target' => $targets['annual'],
                    'targets' => $targets,
                    'performance' => $this->performancePayload($employee, $assignments, $reports),
                    'achievements' => $achievements,
                    'schedule' => $this->schedule($assignments, $reports),
                    'recent_notifications' => $notifications,
                    'unread_notifications' => $notifications->where('is_read', false)->count(),
                    'caught_up' => $actionRequired === [],
                ];
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tasks
    // ─────────────────────────────────────────────────────────────────────────

    public function tasks(User $user, array $filters = []): array
    {
        $employee = $this->requireEmployee($user);
        $query = TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->with(['task', 'progress'])
            ->latest('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
            $query->whereHas('task', function ($q) use ($term) {
                $q->where('title', 'like', $term)->orWhere('task_code', 'like', $term);
            });
        }

        $assignments = $query->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);

        return [
            'tasks' => collect($assignments->items())->map(fn (TaskAssignment $a) => $this->taskShape($a))->values(),
            'meta' => [
                'total' => $assignments->total(),
                'per_page' => $assignments->perPage(),
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
            ],
            'summary' => $this->taskKpis($this->assignments($employee)),
        ];
    }

    public function task(User $user, int $taskAssignmentId): array
    {
        $employee = $this->requireEmployee($user);
        $assignment = TaskAssignment::with(['task', 'progress', 'reviews', 'submissions'])
            ->where('employee_id', $employee->id) // IDOR guard
            ->findOrFail($taskAssignmentId);

        return $this->taskShape($assignment, detailed: true);
    }

    public function updateTaskProgress(User $user, int $assignmentId, int $progress): array
    {
        $employee = $this->requireEmployee($user);
        $assignment = TaskAssignment::where('employee_id', $employee->id)
            ->with('task')
            ->findOrFail($assignmentId);

        if ($progress < 0 || $progress > 100) {
            throw ValidationException::withMessages(['progress' => ['Progress must be between 0 and 100.']]);
        }

        if ($assignment->status === 'completed') {
            throw ValidationException::withMessages([
                'progress' => ['Approved work is locked at 100% and cannot be changed.'],
            ]);
        }

        // Use the shared progress service so the assignment value and the
        // progress history stay in sync with the main task-management surface.
        app(TaskProgressService::class)->updateProgress(
            $assignment,
            (float) $progress,
            null,
            $employee,
        );

        $this->invalidate($employee);

        return $this->taskShape($assignment->fresh(['task']));
    }

    public function submitTask(User $user, int $assignmentId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $assignment = TaskAssignment::where('employee_id', $employee->id)
            ->with('task')
            ->findOrFail($assignmentId);

        if ($assignment->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => ['Approved work cannot be submitted again.'],
            ]);
        }

        // Deadline check (server-side, never trusted from client)
        $due = $this->effectiveDueDate($assignment);
        $isLate = $due !== null && Carbon::now()->isAfter($due);

        $assignment->update([
            'status' => 'submitted',
            // Submission always means the employee considers the work 100%
            // done. completed_at is reserved for the approval timestamp.
            'progress_percentage' => 100,
            'completed_at' => null,
            'remarks' => ($data['notes'] ?? null),
        ]);

        app(\App\Services\TaskManagement\TaskService::class)->syncAggregateStatus($assignment->task);

        $this->invalidate($employee);

        return array_merge($this->taskShape($assignment->fresh()), ['is_late' => $isLate]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reports
    // ─────────────────────────────────────────────────────────────────────────

    public function reports(User $user, array $filters = []): array
    {
        $employee = $this->requireEmployee($user);
        $query = Report::where('employee_id', $employee->id)
            ->with(['task'])
            ->withCount('versions')
            ->orderByDesc('updated_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }
        if (! empty($filters['reporting_period'])) {
            $query->where('reporting_period', 'like', '%'.$filters['reporting_period'].'%');
        }
        if (! empty($filters['ai_summary_status'])) {
            $query->where('ai_summary_status', $filters['ai_summary_status']);
        }
        if (! empty($filters['submitted_from'])) {
            $query->whereDate('submitted_at', '>=', $filters['submitted_from']);
        }
        if (! empty($filters['submitted_to'])) {
            $query->whereDate('submitted_at', '<=', $filters['submitted_to']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('report_type', 'like', $term)
                    ->orWhere('reporting_period', 'like', $term);
            });
        }

        $reports = $query->paginate((int) ($filters['per_page'] ?? 15), ['*'], 'page', (int) ($filters['page'] ?? 1));

        return [
            'reports' => collect($reports->items())->map(fn (Report $r) => $this->reportListShape($r))->values(),
            'meta' => [
                'total' => $reports->total(),
                'per_page' => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
            ],
        ];
    }

    public function report(User $user, int $reportId): array
    {
        $employee = $this->requireEmployee($user);
        $report = Report::where('employee_id', $employee->id) // IDOR guard
            ->with([
                'task',
                'files',
                'versions',
                'aiAnalyses',
                'aiSummaries',
                'aiValidationResults.items',
                'aiSimilarityResults',
                'reviews.reviewer',
                'comments.reviewer',
                'timeline.actor',
            ])
            ->findOrFail($reportId);

        return $this->reportDetailShape($report);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function createReport(User $user, array $data, array $attachments = []): Report
    {
        $employee = $this->requireEmployee($user);
        $this->assertTaskOwnership($employee, (int) $data['task_id']);

        $payload = $this->reportPayload($data, null, true);
        $payload['task_id'] = (int) $data['task_id'];
        $payload['employee_id'] = $employee->id;
        $payload['status'] = ReportStatus::Draft;
        $payload['version'] = 1;
        $payload['ai_summary_status'] = $payload['ai_summary_status'] ?? 'not_generated';

        $report = Report::create($payload);
        $this->storeAttachments($report, $attachments, $user);

        $this->invalidate($employee);

        return $report->fresh(['files', 'task']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function updateReport(User $user, int $reportId, array $data, array $attachments = []): Report
    {
        $employee = $this->requireEmployee($user);
        $report = Report::where('employee_id', $employee->id)
            ->findOrFail($reportId);

        $this->assertEditableDraft($report);

        if (isset($data['task_id'])) {
            $this->assertTaskOwnership($employee, (int) $data['task_id']);
        }

        $payload = $this->reportPayload($data, $report, false);
        if ($payload !== []) {
            $report->update($payload);
        }
        $this->storeAttachments($report, $attachments, $user);

        $this->invalidate($employee);

        return $report->fresh(['files', 'task']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     * @return array<string, mixed>
     */
    public function submitReport(User $user, int $reportId, array $data = [], array $attachments = []): array
    {
        $employee = $this->requireEmployee($user);
        $report = Report::where('employee_id', $employee->id)
            ->findOrFail($reportId);

        $this->assertEditableDraft($report);

        if ($data !== []) {
            $report = $this->updateReport($user, $reportId, $data, $attachments);
        } elseif ($attachments !== []) {
            $this->storeAttachments($report, $attachments, $user);
        }

        $report = $report->fresh(['files', 'task', 'employee']);

        $this->assertSubmitRequirements($report);

        $isLate = $report->deadline && Carbon::now()->isAfter(Carbon::parse($report->deadline));

        $report->forceFill([
            'status' => ReportStatus::Submitted,
            'submitted_at' => Carbon::now(),
            'is_late' => $isLate,
            'ai_summary_status' => $report->ai_summary_status ?: 'generating',
        ])->save();

        $summaryResult = $this->generateAutomaticSummary($report->fresh(['task', 'employee', 'files']));

        $this->dispatchAiPipeline($report);
        $this->invalidate($employee);

        return [
            'report' => $this->reportDetailShape($report->fresh([
                'task', 'files', 'versions', 'aiSummaries', 'reviews.reviewer', 'comments.reviewer', 'timeline.actor',
            ])),
            'ai_summary' => $summaryResult,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateReportSummary(User $user, int $reportId, bool $regenerate = false): array
    {
        $employee = $this->requireEmployee($user);
        $report = Report::where('employee_id', $employee->id)
            ->with(['task', 'employee', 'aiSummaries'])
            ->findOrFail($reportId);

        if ($this->reportStatusValue($report) === ReportStatus::Draft->value && ! $regenerate) {
            throw ValidationException::withMessages([
                'report' => ['Submit the report before generating an AI summary.'],
            ]);
        }

        if ($this->employeeReportSummaryService === null) {
            return $this->summaryUnavailableShape($report, 'AI summary service is not configured.');
        }

        $result = $this->employeeReportSummaryService->generate($report, $regenerate);
        $this->invalidate($employee);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createRevision(User $user, int $reportId, array $data = []): array
    {
        $employee = $this->requireEmployee($user);
        $original = Report::where('employee_id', $employee->id)
            ->findOrFail($reportId);

        if ($this->reportStatusValue($original) !== ReportStatus::RevisionRequired->value) {
            throw ValidationException::withMessages([
                'report' => ['A revision can only be created when revision is required by the reviewer.'],
            ]);
        }

        $original->versions()->create([
            'version_number' => $original->version ?? 1,
            'content' => $original->content ?? $original->description,
            'status' => $this->reportStatusValue($original),
            'submitted_at' => $original->submitted_at,
        ]);

        $newVersion = ($original->version ?? 1) + 1;
        $payload = $this->reportPayload($data, $original, false);
        $payload['status'] = ReportStatus::Draft;
        $payload['version'] = $newVersion;
        $payload['submitted_at'] = null;
        $payload['approved_at'] = null;
        $payload['rejected_at'] = null;
        $payload['ai_summary_status'] = 'not_generated';
        $payload['ai_summary_generated_at'] = null;
        if (array_key_exists('revision_notes', $data)) {
            $payload['remarks'] = $data['revision_notes'];
        }

        $original->update($payload);

        $this->invalidate($employee);

        return $this->reportDetailShape($original->fresh([
            'task', 'files', 'versions', 'aiSummaries', 'reviews.reviewer', 'comments.reviewer', 'timeline.actor',
        ]));
    }

    public function downloadReportFile(User $user, int $reportId, int $fileId)
    {
        $employee = $this->requireEmployee($user);
        $report = Report::where('employee_id', $employee->id)->findOrFail($reportId);
        $file = $report->files()->whereKey($fileId)->firstOrFail();
        $disk = $file->storage_disk ?: config('filesystems.default', 'public');
        $path = $file->file_path ?: $file->stored_filename;

        abort_unless($path && Storage::disk($disk)->exists($path), 404, 'File not found.');

        return Storage::disk($disk)->download($path, $file->original_filename ?? $file->file_name ?? 'attachment');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calendar
    // ─────────────────────────────────────────────────────────────────────────

    public function calendar(User $user, ?int $year = null, ?int $month = null): array
    {
        $employee = $this->requireEmployee($user);
        $assignments = $this->assignments($employee);
        $reports = $this->employeeReports($employee);

        // Real records only: the employee's own task/report deadlines plus the
        // published announcements this employee is audience to. Announcements
        // are the institution's event/holiday/deadline records — there is no
        // separate meetings or holidays table in this schema.
        $events = $this->calendarEvents($assignments, $reports)
            ->concat($this->announcementEvents($user));

        if ($year !== null && $month !== null) {
            $events = $events->filter(
                fn (array $event) => $event['date'] !== null
                    && (int) substr((string) $event['date'], 0, 4) === $year
                    && (int) substr((string) $event['date'], 5, 2) === $month
            );
        }

        // Nearest date first; time breaks ties within the same day.
        return [
            'events' => $events
                ->sortBy([['date', 'asc'], ['time', 'asc']])
                ->values()
                ->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Performance
    // ─────────────────────────────────────────────────────────────────────────

    public function performance(User $user): array
    {
        $employee = $this->requireEmployee($user);
        $assignments = $this->assignments($employee);

        $payload = $this->performancePayload($employee, $assignments, $this->employeeReports($employee));

        return [
            'performance' => $payload['score'],
            'completion_rate' => $payload['metrics']['completion_rate']['value'],
            'on_time_rate' => $payload['metrics']['on_time_rate']['value'],
            'approval_rate' => $payload['metrics']['approval_rate']['value'],
            'disclaimer' => $payload['disclaimer'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Targets
    // ─────────────────────────────────────────────────────────────────────────

    public function targets(User $user): array
    {
        $employee = $this->requireEmployee($user);

        $payload = $this->targetsPayload($employee);

        return [
            'targets' => $payload['items'],
            'summary' => $payload['summary'],
            'annual' => $payload['annual'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Achievements
    // ─────────────────────────────────────────────────────────────────────────

    public function achievements(User $user): array
    {
        $employee = $this->requireEmployee($user);

        $payload = $this->achievementsPayload($employee);

        return [
            'badges' => $payload['items'],
            'monthly' => $payload['monthly'],
            'total' => $payload['total'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Notifications
    // ─────────────────────────────────────────────────────────────────────────

    public function notifications(User $user): array
    {
        $items = Notification::query()
            ->forUser($user->id)
            ->latest('created_at')
            ->take(20)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'is_read' => $n->read_at !== null,
                'read_at' => optional($n->read_at)->toIso8601String(),
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
            'name' => $employee->full_name ?? ($employee->first_name.' '.$employee->last_name),
            'email' => $user->email,
            'employee_no' => $employee->employee_no ?? $employee->employee_number ?? null,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name ?? $employee->position_title ?? null,
            'employee_type' => $employee->employee_type ?? null,
            'status' => $employee->status ?? null,
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

    /**
     * Dashboard payloads are cached per calendar month, so a plain key forget
     * cannot reach every variant. A per-employee version token is bumped
     * instead, which retires all month-scoped entries at once.
     */
    public function invalidate(Employee $employee): void
    {
        Cache::forever($this->key($employee, 'version'), $this->cacheVersion($employee) + 1);

        foreach (['calendar', 'performance', 'targets', 'achievements'] as $segment) {
            Cache::forget($this->key($employee, $segment));
        }
    }

    private function cacheVersion(Employee $employee): int
    {
        return (int) (Cache::get($this->key($employee, 'version')) ?? 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function requireEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if ($employee === null) {
            abort(403, 'No employee profile linked to this account.');
        }

        return $employee;
    }

    /**
     * Pivot columns are not datetime-cast, so they arrive as strings. Normalise
     * anything date-like into an ISO-8601 string.
     */
    private function toIso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function assignments(Employee $employee): Collection
    {
        return TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->with(['task', 'progress'])
            ->get();
    }

    /**
     * Effective deadline for an assignment: the assignment-level due date when
     * present, otherwise the parent task's due date. The model accessor only
     * knows about `task_assignments.due_date`, so the fallback lives here to
     * avoid changing model behaviour other features depend on.
     */
    private function effectiveDueDate(TaskAssignment $assignment): ?Carbon
    {
        $due = $assignment->due_date ?? $assignment->task?->due_date;

        return $due instanceof Carbon ? $due->copy() : null;
    }

    /** Overdue test that honours the effective deadline (task-level fallback). */
    private function assignmentIsOverdue(TaskAssignment $assignment): bool
    {
        $due = $this->effectiveDueDate($assignment);

        if ($due === null || $assignment->completed_at !== null) {
            return false;
        }

        return in_array($assignment->status, self::TASK_CLOSED, true)
            ? false
            : $due->isPast();
    }

    /**
     * Percentage that returns null when there is nothing to measure, so the UI
     * can say "no data yet" instead of showing a misleading 0%.
     */
    private function rate(int|float|null $numerator, int|float|null $denominator): ?float
    {
        if ($denominator === null || $denominator <= 0 || $numerator === null) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function taskKpis(Collection $assignments): array
    {
        $total = $assignments->count();
        $completed = $assignments->whereIn('status', self::TASK_COMPLETED)->count();
        $overdue = $assignments->filter(fn (TaskAssignment $a) => $this->assignmentIsOverdue($a))->count();

        return [
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $assignments->whereIn('status', self::TASK_NOT_STARTED)->count(),
            'in_progress_tasks' => $assignments->whereIn('status', self::TASK_IN_PROGRESS)->count(),
            'submitted_tasks' => $assignments->whereIn('status', self::TASK_SUBMITTED)->count(),
            'overdue_tasks' => $overdue,
            'completion_rate' => $this->rate($completed, $total),
        ];
    }

    /**
     * Report KPIs. Status is cast to ReportStatus, and Collection::whereIn does
     * a loose in_array() that never matches a backed enum against a string — the
     * enum instances are therefore required here.
     */
    private function reportKpis(Collection $reports): array
    {
        $total = $reports->count();
        $approved = $reports->where('status', ReportStatus::Approved)->count();
        $draft = $reports->where('status', ReportStatus::Draft)->count();
        $late = $reports->filter(fn (Report $r) => $this->reportIsLate($r))->count();
        $reviewable = $reports->whereIn('status', self::REPORT_OPEN)->count();

        return [
            'total_reports' => $total,
            'submitted_reports' => $reports->whereIn('status', self::REPORT_OPEN)->count(),
            'approved_reports' => $approved,
            'draft_reports' => $draft,
            'revision_required' => $reports->where('status', ReportStatus::RevisionRequired)->count(),
            'late_reports' => $late,
            'pending_reports' => max(0, $reviewable - $draft),
            'approval_rate' => $this->rate($approved, $total),
            'report_completion_rate' => $this->rate($total - $draft, $total),
        ];
    }

    private function onTimeRate(Collection $assignments): ?float
    {
        $finished = $assignments->whereIn('status', self::TASK_COMPLETED);

        if ($finished->isEmpty()) {
            return null;
        }

        $onTime = $finished->filter(function (TaskAssignment $a) {
            $due = $this->effectiveDueDate($a);

            return $a->completed_at !== null && $due !== null && $a->completed_at->lte($due);
        })->count();

        return $this->rate($onTime, $finished->count());
    }

    /** Share of finished reports that were submitted on or before the deadline. */
    private function onTimeReportRate(Collection $reports): ?float
    {
        $submitted = $reports->filter(
            fn (Report $r) => ! in_array($r->status, [ReportStatus::Draft, ReportStatus::Cancelled], true)
        );

        if ($submitted->isEmpty()) {
            return null;
        }

        $onTime = $submitted->filter(fn (Report $r) => ! $this->reportIsLate($r))->count();

        return $this->rate($onTime, $submitted->count());
    }

    private function reportIsLate(Report $report): bool
    {
        if ($report->is_late) {
            return true;
        }

        return $report->deadline !== null
            && $report->submitted_at !== null
            && $report->submitted_at->gt(Carbon::parse($report->deadline)->endOfDay());
    }

    /** Days until a deadline: 0 = today, negative = overdue. */
    private function daysUntil(?Carbon $deadline): ?int
    {
        if ($deadline === null) {
            return null;
        }

        return (int) Carbon::now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false);
    }

    /** Urgency bucket so the UI never has to recompute date maths. */
    private function urgency(?Carbon $deadline, bool $done = false): string
    {
        if ($done) {
            return 'done';
        }

        $days = $this->daysUntil($deadline);

        return match (true) {
            $days === null => 'none',
            $days < 0 => 'overdue',
            $days === 0 => 'today',
            $days === 1 => 'tomorrow',
            $days <= 3 => 'soon',
            default => 'later',
        };
    }

    /**
     * Open work due within the window. Includes work due *today* so a deadline
     * is never silently dropped from the employee's view.
     */
    private function upcomingDeadlines(Collection $assignments, Collection $reports): array
    {
        $tasks = $assignments
            ->filter(function (TaskAssignment $a) {
                $due = $this->effectiveDueDate($a);

                if ($due === null || in_array($a->status, self::TASK_CLOSED, true)) {
                    return false;
                }

                $days = $this->daysUntil($due);

                return $days !== null && $days >= 0 && $days <= self::DEADLINE_WINDOW_DAYS;
            })
            ->map(fn (TaskAssignment $a) => $this->deadlineShape($a));

        $reports = $reports
            ->filter(function (Report $r) {
                if ($r->deadline === null || in_array($r->status, self::REPORT_EMPLOYEE_ACTION, true) === false && in_array($r->status, [ReportStatus::Approved, ReportStatus::Cancelled], true)) {
                    return false;
                }

                $days = $this->daysUntil(Carbon::parse($r->deadline));

                return $days !== null && $days >= 0 && $days <= self::DEADLINE_WINDOW_DAYS;
            })
            ->map(fn (Report $r) => [
                'type' => 'report',
                'id' => $r->id,
                'title' => $r->title,
                'deadline' => Carbon::parse($r->deadline)->toIso8601String(),
                'deadline_date' => Carbon::parse($r->deadline)->toDateString(),
                'days_remaining' => $this->daysUntil(Carbon::parse($r->deadline)),
                'status' => $r->status->value,
                'urgency' => $this->urgency(Carbon::parse($r->deadline), $r->status === ReportStatus::Approved),
            ]);

        return $tasks->concat($reports)
            ->sortBy('deadline')
            ->take(self::DEADLINE_WINDOW_DAYS > 0 ? 8 : 8)
            ->values()
            ->all();
    }

    private function deadlineShape(TaskAssignment $a): array
    {
        $due = $this->effectiveDueDate($a);
        $days = $this->daysUntil($due);

        return [
            'type' => 'task',
            'id' => $a->id,
            'task_id' => $a->task_id,
            'title' => $a->task?->title ?? "Task #{$a->task_id}",
            'deadline' => $due?->toIso8601String(),
            'deadline_date' => $due?->toDateString(),
            'days_remaining' => $days,
            'status' => $a->status,
            'urgency' => $this->urgency($due),
        ];
    }

    private function overdueTasks(Collection $assignments): array
    {
        return $assignments
            ->filter(fn (TaskAssignment $a) => $this->assignmentIsOverdue($a))
            ->sortBy(fn (TaskAssignment $a) => $this->effectiveDueDate($a)?->timestamp ?? PHP_INT_MAX)
            ->take(self::DASHBOARD_TASK_LIMIT)
            ->map(function (TaskAssignment $a) {
                $due = $this->effectiveDueDate($a);
                $days = $this->daysUntil($due);

                return [
                    'type' => 'task',
                    'id' => $a->id,
                    'task_id' => $a->task_id,
                    'title' => $a->task?->title ?? "Task #{$a->task_id}",
                    'deadline' => $due?->toIso8601String(),
                    'days_overdue' => $days === null ? 0 : abs($days),
                    'status' => $a->status,
                    'urgency' => 'overdue',
                ];
            })
            ->values()
            ->all();
    }

    private function overdueReports(Collection $reports): array
    {
        return $reports
            ->filter(fn (Report $r) => $this->reportIsLate($r))
            ->take(self::DASHBOARD_REPORT_LIMIT)
            ->map(fn (Report $r) => [
                'type' => 'report',
                'id' => $r->id,
                'title' => $r->title,
                'deadline' => $r->deadline?->toIso8601String(),
                'status' => $r->status->value,
                'urgency' => 'overdue',
                'action' => $this->reportAction($r),
            ])
            ->values()
            ->all();
    }

    private function recentTasks(Collection $assignments): array
    {
        return $assignments
            ->sortByDesc(fn (TaskAssignment $a) => $a->updated_at?->timestamp ?? $a->created_at?->timestamp ?? 0)
            ->take(self::DASHBOARD_TASK_LIMIT)
            ->map(fn (TaskAssignment $a) => $this->taskSummaryShape($a))
            ->values()
            ->all();
    }

    private function taskSummaryShape(TaskAssignment $a): array
    {
        $due = $this->effectiveDueDate($a);

        return [
            'assignment_id' => $a->id,
            'task_id' => $a->task_id,
            'title' => $a->task?->title ?? "Task #{$a->task_id}",
            'description' => $a->task?->description,
            'priority' => $a->task?->priority,
            'status' => $a->status,
            'progress' => (int) ($a->progress_percentage ?? 0),
            'deadline' => $due?->toIso8601String(),
            'deadline_date' => $due?->toDateString(),
            'assigned_at' => $a->assigned_at?->toIso8601String(),
            'is_overdue' => $this->assignmentIsOverdue($a),
            'urgency' => $this->urgency($due, in_array($a->status, self::TASK_CLOSED, true)),
        ];
    }

    private function recentReports(Collection $reports): array
    {
        return $reports
            ->take(self::DASHBOARD_REPORT_LIMIT)
            ->map(fn (Report $r) => $this->reportSummaryShape($r))
            ->values()
            ->all();
    }

    private function reportSummaryShape(Report $r): array
    {
        $deadline = $r->deadline?->copy();
        $done = $r->status === ReportStatus::Approved;
        $waiting = ! $done && ! in_array($r->status, self::REPORT_EMPLOYEE_ACTION, true);

        return [
            'id' => $r->id,
            'title' => $r->title,
            'task_id' => $r->task_id,
            'task_title' => $r->task?->title,
            'status' => $r->status->value,
            'review_status' => $r->review_status,
            'submitted_at' => $r->submitted_at?->toIso8601String(),
            'deadline' => $deadline?->toIso8601String(),
            'is_late' => $this->reportIsLate($r),
            'needs_action' => in_array($r->status, self::REPORT_EMPLOYEE_ACTION, true),
            'action' => $this->reportAction($r),
            // A submitted report is with the reviewer, so a past deadline is not
            // an employee-facing urgency any more.
            'urgency' => match (true) {
                $done => 'done',
                $waiting => 'waiting',
                default => $this->urgency($deadline),
            },
        ];
    }

    private function reportAction(Report $r): ?string
    {
        return match ($r->status) {
            ReportStatus::Draft => 'edit',
            ReportStatus::RevisionRequired => 'revise',
            default => null,
        };
    }

    /**
     * Everything the employee still owns: revisions, unsubmitted drafts, tasks
     * already past due and work due within 48 hours. Empty array means the
     * employee is genuinely caught up.
     */
    private function actionRequired(Collection $assignments, Collection $reports): array
    {
        $items = [];

        foreach ($reports as $r) {
            if (! in_array($r->status, self::REPORT_EMPLOYEE_ACTION, true)) {
                continue;
            }

            $items[] = [
                'kind' => 'report',
                'id' => $r->id,
                'title' => $r->title,
                'reason' => $r->status === ReportStatus::RevisionRequired
                    ? 'Your report was returned for revision.'
                    : 'This report has not been submitted yet.',
                'action' => $this->reportAction($r),
                'action_label' => $r->status === ReportStatus::RevisionRequired ? 'Edit Report' : 'Continue Report',
                'severity' => $r->status === ReportStatus::RevisionRequired ? 'high' : 'medium',
            ];
        }

        foreach ($reports as $r) {
            if (! $this->reportIsLate($r) || in_array($r->status, [ReportStatus::Approved], true)) {
                continue;
            }

            $items[] = [
                'kind' => 'report',
                'id' => $r->id,
                'title' => $r->title,
                'reason' => 'Submitted after the report deadline.',
                'action' => null,
                'action_label' => 'View Report',
                'severity' => 'high',
            ];
        }

        foreach ($assignments as $a) {
            $due = $this->effectiveDueDate($a);
            $days = $this->daysUntil($due);
            $isOpen = ! in_array($a->status, self::TASK_CLOSED, true);

            if (! $isOpen || $due === null) {
                continue;
            }

            if ($this->assignmentIsOverdue($a)) {
                $items[] = [
                    'kind' => 'task',
                    'id' => $a->id,
                    'title' => $a->task?->title ?? "Task #{$a->task_id}",
                    'reason' => 'This task is past its deadline.',
                    'action' => 'view',
                    'action_label' => 'View Task',
                    'severity' => 'high',
                ];

                continue;
            }

            if ($days !== null && $days <= 2) {
                $items[] = [
                    'kind' => 'task',
                    'id' => $a->id,
                    'title' => $a->task?->title ?? "Task #{$a->task_id}",
                    'reason' => $days === 0
                        ? 'Due today.'
                        : ($days === 1 ? 'Due tomorrow.' : 'Due in 2 days.'),
                    'action' => 'view',
                    'action_label' => 'View Task',
                    'severity' => $days === 0 ? 'high' : 'medium',
                ];
            }
        }

        $severityRank = ['high' => 0, 'medium' => 1, 'low' => 2];

        usort($items, fn (array $a, array $b) => $severityRank[$a['severity']] <=> $severityRank[$b['severity']]);

        return array_slice($items, 0, self::DASHBOARD_ACTION_LIMIT);
    }

    /** Month-scoped rollup computed from the same collections as the KPIs. */
    private function monthlyProgress(Collection $assignments, Collection $reports, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $assignedInMonth = $assignments->filter(
            fn (TaskAssignment $a) => $a->assigned_at !== null && $a->assigned_at->betweenIncluded($start, $end)
        );

        $completedInMonth = $assignments->filter(
            fn (TaskAssignment $a) => $a->completed_at !== null && $a->completed_at->betweenIncluded($start, $end)
        );

        $submittedInMonth = $reports->filter(
            fn (Report $r) => $r->submitted_at !== null && $r->submitted_at->betweenIncluded($start, $end)
        );

        $approvedInMonth = $reports->filter(
            fn (Report $r) => $r->approved_at !== null && $r->approved_at->betweenIncluded($start, $end)
        );

        $lateInMonth = $submittedInMonth->filter(fn (Report $r) => $this->reportIsLate($r))->count();

        return [
            'year' => $year,
            'month' => $month,
            'label' => $start->format('F Y'),
            'tasks_assigned' => $assignedInMonth->count(),
            'tasks_completed' => $completedInMonth->count(),
            'reports_submitted' => $submittedInMonth->count(),
            'reports_approved' => $approvedInMonth->count(),
            'late_submissions' => $lateInMonth,
            'on_time_submissions' => $submittedInMonth->count() - $lateInMonth,
            'completion_rate' => $this->rate($completedInMonth->count(), $assignedInMonth->count()),
        ];
    }

    /**
     * Target payload, computed in ONE place by EmployeeTargetService so the
     * dashboard widget, the performance metric and the My Targets workspace all
     * agree on how far a target has got. Progress is derived from real evidence
     * (sub-target weighted average, else linked-task completion) because
     * `employee_targets` has no `actual_value` column.
     */
    private function targetsPayload(Employee $employee): array
    {
        return $this->employeeTargetService->payloadForEmployee($employee);
    }

    private function achievementsPayload(Employee $employee): array
    {
        $badges = $employee->badges()
            ->orderByDesc('employee_badges.awarded_at')
            ->orderByDesc('employee_badges.id')
            ->get()
            ->map(fn ($badge) => [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'criteria' => $badge->criteria,
                'earned_at' => $this->toIso($badge->pivot?->awarded_at ?? $badge->pivot?->created_at ?? $badge->awarded_at),
            ])
            ->values();

        $monthly = $employee->monthlyAchievements()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($m) => [
                'year' => $m->year,
                'month' => $m->month,
                'month_name' => $m->month ? Carbon::create((int) $m->year, (int) $m->month, 1)->format('F') : null,
                'completion' => $m->completion_rate,
                'on_time' => $m->on_time_rate,
                'rank' => $m->rank,
                'badge' => $m->badge,
                'achievement' => $m->achievement,
            ])
            ->values();

        return [
            'items' => $badges->take(self::DASHBOARD_ACHIEVEMENT_LIMIT),
            'badges' => $badges,
            'monthly' => $monthly,
            'total' => $badges->count(),
        ];
    }

    /**
     * Only metrics that genuinely exist are emitted. Each entry carries its own
     * `value`, which is null when there is no data to report.
     */
    private function performancePayload(Employee $employee, Collection $assignments, Collection $reports): array
    {
        try {
            $score = $this->performanceCalculation->calculate(authEmployee: $employee);
        } catch (\Throwable) {
            $score = null;
        }

        $taskKpis = $this->taskKpis($assignments);
        $reportKpi = $this->reportKpis($reports);
        $targets = $this->targetsPayload($employee);

        $metrics = [
            'performance_score' => [
                'label' => 'Productivity Score',
                'value' => is_array($score) ? ($score['overall_score'] ?? $score['score'] ?? null) : null,
                'source' => 'ai',
            ],
            'completion_rate' => [
                'label' => 'Task Completion Rate',
                'value' => $taskKpis['completion_rate'],
                'source' => 'system',
            ],
            'on_time_rate' => [
                'label' => 'On-Time Submission Rate',
                'value' => $this->onTimeRate($assignments),
                'source' => 'system',
            ],
            'on_time_report_rate' => [
                'label' => 'On-Time Report Rate',
                'value' => $this->onTimeReportRate($reports),
                'source' => 'system',
            ],
            'approval_rate' => [
                'label' => 'Report Approval Rate',
                'value' => $reportKpi['approval_rate'],
                'source' => 'system',
            ],
            'target_achievement' => [
                'label' => 'Target Achievement',
                'value' => $targets['annual']['progress_percent'],
                'source' => 'system',
            ],
        ];

        return [
            'score' => $score,
            'metrics' => $metrics,
            'available' => collect($metrics)->contains(fn (array $m) => $m['value'] !== null),
            'disclaimer' => 'Performance metrics are calculated from recorded system data. '
                .'AI-generated summaries are advisory only and never a source of truth.',
        ];
    }

    /** Upcoming task and report deadlines, closest first. */
    private function schedule(Collection $assignments, Collection $reports): array
    {
        $events = $this->calendarEvents($assignments, $reports)
            ->filter(fn (array $e) => $e['date'] !== null && $e['date'] >= Carbon::now()->toDateString())
            ->sortBy('date')
            ->take(self::DASHBOARD_SCHEDULE_LIMIT)
            ->values()
            ->all();

        return $events;
    }

    private function calendarEvents(Collection $assignments, Collection $reports): Collection
    {
        $taskEvents = $assignments
            ->map(function (TaskAssignment $a) {
                $due = $this->effectiveDueDate($a);

                if ($due === null) {
                    return null;
                }

                return [
                    'id' => $a->id,
                    'type' => 'task',
                    'category' => 'Task',
                    'title' => $a->task?->title ?? "Task #{$a->task_id}",
                    'description' => null,
                    'date' => $due->toDateString(),
                    'time' => null,
                    'status' => $a->status,
                    'is_overdue' => $this->assignmentIsOverdue($a),
                    'link' => "/employee/tasks/{$a->task_id}",
                ];
            })
            ->filter()
            ->values();

        $reportEvents = $reports
            ->filter(fn (Report $r) => $r->deadline !== null)
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'type' => 'report',
                'category' => 'Report',
                'title' => $r->title,
                'description' => null,
                'date' => $r->deadline->toDateString(),
                'time' => null,
                'status' => $r->status->value,
                'is_overdue' => $this->reportIsLate($r),
                'link' => "/employee/reports/{$r->id}",
            ])
            ->values();

        return $taskEvents->concat($reportEvents)->sortBy('date')->values();
    }

    /**
     * Published announcements the employee is audience to, placed on their
     * event window start. Audience scoping is delegated to
     * Announcement::scopeForUser, so an announcement only appears for the
     * employees it targets. Any residual null start date is dropped rather
     * than shown without a date.
     */
    private function announcementEvents(User $user): Collection
    {
        return Announcement::query()
            ->forUser($user)
            ->whereNotNull('start_date')
            ->get()
            ->map(function (Announcement $announcement) {
                $category = $announcement->category ?: 'general';

                return [
                    'id' => $announcement->id,
                    'type' => 'announcement',
                    'category' => ucfirst($category),
                    'title' => $announcement->title,
                    'description' => $announcement->message,
                    'date' => $announcement->start_date?->toDateString(),
                    'time' => $announcement->start_date?->format('H:i'),
                    'status' => 'published',
                    'is_overdue' => false,
                    'link' => null,
                ];
            })
            ->filter(fn (array $event) => $event['date'] !== null)
            ->values();
    }

    private function recentNotifications(?User $user): Collection
    {
        if ($user === null) {
            return collect();
        }

        return Notification::query()
            ->forUser($user->id)
            ->latest('created_at')
            ->take(self::DASHBOARD_NOTIFICATION_LIMIT)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'is_read' => $n->read_at !== null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->values();
    }

    /** Reuse an already-loaded report collection instead of re-querying. */
    private function employeeReports(Employee $employee): Collection
    {
        return $employee->reports()->with('task')->orderByDesc('created_at')->get();
    }

    private function taskShape(TaskAssignment $a, bool $detailed = false): array
    {
        $due = $this->effectiveDueDate($a);
        $submittedAt = $a->relationLoaded('submissions')
            ? $a->submissions->sortByDesc('submitted_at')->first()?->submitted_at
            : null;

        $base = [
            'id' => $a->id,
            'task_id' => $a->task_id,
            'task_code' => $a->task?->task_code,
            'title' => $a->task?->title,
            'description' => $detailed ? ($a->task?->description) : null,
            'priority' => $a->task?->priority,
            'assigned_date' => optional($a->assigned_at)->toIso8601String(),
            'deadline' => $due?->toIso8601String(),
            'progress' => (int) ($a->progress_percentage ?? 0),
            'status' => $a->status,
            'is_overdue' => $this->assignmentIsOverdue($a),
            'is_late' => $submittedAt !== null && $due !== null ? $submittedAt->gt($due) : false,
            'submitted_at' => optional($submittedAt)->toIso8601String(),
            // completed_at is the approval timestamp, not the submission time.
            'completed_at' => optional($a->completed_at)->toIso8601String(),
        ];

        if ($detailed) {
            $base['requirements'] = $a->task?->required_output ?? null;
            $base['instructions'] = $a->task?->required_output ?? null;
            $base['target'] = $a->task?->target ?? null;
            $base['notes'] = $a->remarks ?? null;
            $base['late_reason'] = $a->late_reason ?? null;
            $base['reviews'] = $a->reviews?->map(fn ($r) => [
                'reviewer' => $r->reviewer?->name,
                'decision' => $r->decision,
                'comments' => $r->comments,
                'reviewed_at' => optional($r->reviewed_at)->toIso8601String(),
            ])->values() ?? [];
        }

        return $base;
    }

    private function reportListShape(Report $r): array
    {
        $latestAnalysis = $r->relationLoaded('aiAnalyses') ? $r->aiAnalyses->sortByDesc('id')->first() : $r->aiAnalyses()->latest('id')->first();
        $latestValidation = $r->relationLoaded('aiValidationResults') ? $r->aiValidationResults->sortByDesc('id')->first() : $r->aiValidationResults()->latest('id')->first();
        $status = $this->reportStatusValue($r);
        $aiSummaryStatus = $r->ai_summary_status ?: ($status === ReportStatus::Draft->value ? 'not_generated' : 'not_generated');

        return [
            'id' => $r->id,
            'title' => $r->title,
            'report_type' => $r->report_type,
            'reporting_period' => $r->reporting_period,
            'task_id' => $r->task_id,
            'task_title' => $r->task?->title,
            'submitted_at' => optional($r->submitted_at)->toIso8601String(),
            'created_at' => optional($r->created_at)->toIso8601String(),
            'updated_at' => optional($r->updated_at)->toIso8601String(),
            'status' => $status,
            'version' => $r->version ?? 1,
            'is_late' => (bool) ($r->is_late ?? false),
            'ai_status' => $r->ai_status ?: ($latestAnalysis?->status ?? ($r->ai_processing_status ?? 'not_requested')),
            'ai_summary_status' => $aiSummaryStatus,
            'ai_summary_generated_at' => optional($r->ai_summary_generated_at)->toIso8601String(),
            'review_status' => $r->review_status ?? null,
            'is_revision' => ($r->version ?? 1) > 1,
            'advisory_label' => 'AI-Assisted Validation',
            'disclaimer' => 'AI analysis is advisory. Final validation requires authorized human review.',
        ];
    }

    private function reportDetailShape(Report $report): array
    {
        $latestAnalysis = $report->aiAnalyses()->sortByDesc('id')->first();
        $latestSummary = $report->aiSummaries()->sortByDesc('id')->first();
        $latestValidation = $report->aiValidationResults()->sortByDesc('id')->first();
        $latestSimilarity = $report->aiSimilarityResults()->sortByDesc('id')->first();
        $latestOcr = method_exists($report, 'ocrProcessings')
            ? $report->ocrProcessings()->latest('id')->first()
            : null;

        // OCR section
        $ocr = null;
        if ($latestOcr) {
            $confidence = $latestOcr->confidence ?? null;
            $confLabel = $confidence === null ? null :
                ($confidence >= 80 ? 'high' : ($confidence >= 50 ? 'medium' : 'low'));
            $ocr = [
                'status' => $latestOcr->status,
                'extracted_text' => $latestOcr->extracted_text,
                'confidence' => $confidence,
                'confidence_label' => $confLabel,
                'warning' => ($confLabel === 'low') ? 'Some extracted text may require manual verification.' : null,
            ];
        }

        // AI Summary (prefer denormalized employee summary payload, fall back to existing analysis summary)
        $aiSummary = null;
        $denormalized = $this->decodeSummary($report->ai_summary);
        if ($denormalized !== []) {
            $aiSummary = [
                'status' => $report->ai_summary_status ?? 'generated',
                'executive_summary' => $denormalized['executive_summary'] ?? null,
                'summary_of_accomplishments' => $denormalized['accomplishments'] ?? [],
                'completed_tasks_summary' => $denormalized['completed_tasks'] ?? [],
                'challenges_encountered' => $denormalized['challenges'] ?? [],
                'recommendations' => $denormalized['recommendations'] ?? [],
                'next_steps' => $denormalized['next_steps'] ?? [],
                'key_points' => $denormalized['completed_tasks'] ?? $denormalized['key_points'] ?? [],
                'accomplishments' => $denormalized['accomplishments'] ?? [],
                'issues' => $denormalized['challenges'] ?? [],
                'generated_at' => optional($report->ai_summary_generated_at)->toIso8601String(),
                'disclaimer' => $denormalized['disclaimer'] ?? 'AI-generated summary based on the submitted report. Verify against the original report before making official decisions.',
            ];
        } elseif ($latestSummary) {
            $raw = is_string($latestSummary->summary) ? json_decode($latestSummary->summary, true) : $latestSummary->summary;
            $raw = is_array($raw) ? $raw : [];
            $aiSummary = [
                'status' => $report->ai_summary_status ?? ($latestAnalysis?->status ?? 'completed'),
                'executive_summary' => $raw['executive_summary'] ?? ($latestSummary->summary ?? null),
                'summary_of_accomplishments' => $raw['accomplishments'] ?? ($latestSummary->key_accomplishments ?? []),
                'completed_tasks_summary' => $raw['completed_tasks'] ?? ($latestSummary->key_activities ?? []),
                'challenges_encountered' => $raw['challenges'] ?? ($latestSummary->issues ?? []),
                'recommendations' => $raw['recommendations'] ?? ($latestSummary->recommendations ?? []),
                'next_steps' => $raw['next_steps'] ?? ($latestSummary->action_items ?? []),
                'key_points' => $raw['key_points'] ?? ($latestSummary->key_findings ?? []),
                'accomplishments' => $raw['accomplishments'] ?? ($latestSummary->key_accomplishments ?? []),
                'issues' => $raw['issues'] ?? ($latestSummary->issues ?? []),
                'generated_at' => optional($latestSummary->generated_at)->toIso8601String(),
                'disclaimer' => $raw['disclaimer'] ?? 'AI-generated summary. Verify against the original report.',
            ];
        }

        // AI Validation
        $aiValidation = null;
        if ($latestValidation) {
            $items = $latestValidation->items ?? $latestValidation->validationItems ?? collect();
            $reqChecks = collect($items)->where('type', 'requirement')->map(fn ($i) => [
                'requirement' => $i->requirement ?? $i->description ?? '',
                'status' => $i->status ?? 'needs_review',
                'note' => $i->note ?? null,
            ]);
            $refResults = collect($items)->where('type', 'reference')->map(fn ($i) => [
                'reference' => $i->reference ?? $i->description ?? '',
                'status' => $i->status ?? 'needs_review',
                'note' => $i->note ?? null,
            ]);

            $simResult = 'no_match';
            $simLabel = 'No Significant Match';
            if ($latestSimilarity) {
                $score = $latestSimilarity->similarity_score ?? 0;
                if ($score >= 70) {
                    $simResult = 'potential_match';
                    $simLabel = 'Potential Similarity Detected';
                } elseif ($score >= 40) {
                    $simResult = 'needs_review';
                    $simLabel = 'Similarity Needs Review';
                }
            }

            $aiValidation = [
                'status' => $latestValidation->status ?? 'completed',
                'requirement_checks' => $reqChecks->values(),
                'reference_results' => $refResults->values(),
                'similarity' => [
                    'result' => $simResult,
                    'label' => $simLabel,
                    'note' => 'Potential similarity detected. This is an automated advisory check — not a final determination.',
                ],
                'flags' => $latestValidation->flags ?? [],
                'overall_score' => $latestValidation->overall_score,
                'advisory_label' => 'AI-Assisted Validation',
                'disclaimer' => 'AI analysis is advisory. Final validation requires authorized human review.',
            ];
        }

        $reviews = $report->reviews->sortBy('created_at')->map(fn ($rv) => [
            'reviewer' => $rv->reviewer?->name,
            'decision' => $rv->decision ?? $rv->status ?? null,
            'comments' => $rv->comments ?? $rv->notes ?? null,
            'date' => optional($rv->reviewed_at ?? $rv->created_at)->toIso8601String(),
        ])->values();

        $commentFeedback = $report->comments->where('is_visible_to_employee', true)->sortBy('created_at')->map(fn ($c) => [
            'id' => $c->id,
            'reviewer' => $c->reviewer?->name,
            'section' => $c->section_key ?? $c->section ?? null,
            'comment' => $c->comment ?? $c->body ?? null,
            'is_resolved' => (bool) ($c->is_resolved ?? false),
            'created_at' => optional($c->created_at)->toIso8601String(),
        ])->values();

        $versions = $report->versions->sortBy('version_number')->map(fn ($v) => [
            'version' => $v->version_number ?? $v->version ?? null,
            'status' => $v->status,
            'submitted_at' => optional($v->submitted_at)->toIso8601String(),
            'created_at' => optional($v->created_at)->toIso8601String(),
            'change_summary' => $v->change_summary ?? null,
            'is_current' => false,
        ])->values();

        $timeline = $report->timeline->sortBy('created_at')->map(fn ($e) => [
            'id' => $e->id,
            'event_type' => $e->event_type ?? $e->type ?? null,
            'description' => $e->description ?? $e->message ?? null,
            'actor' => $e->actor?->name ?? null,
            'created_at' => optional($e->created_at)->toIso8601String(),
            'metadata' => $e->metadata ?? null,
        ])->values();

        return array_merge($this->reportListShape($report), [
            'report_description' => $report->content ?? $report->description,
            'content' => $report->content ?? $report->description,
            'accomplishments' => $report->accomplishments,
            'completed_tasks' => $report->completed_tasks_summary,
            'completed_tasks_summary' => $report->completed_tasks_summary,
            'challenges_encountered' => $report->challenges_encountered,
            'next_plans' => $report->next_plans,
            'references' => $report->references ?? $report->remarks,
            'submission_date' => optional($report->submission_date ?? $report->submitted_at)->toDateString(),
            'deadline' => optional($report->deadline)->toDateString(),
            'approved_at' => optional($report->approved_at)->toIso8601String(),
            'rejected_at' => optional($report->rejected_at)->toIso8601String(),
            'files' => $report->files->map(fn ($f) => [
                'id' => $f->id,
                'file_name' => $f->original_filename ?? $f->file_name ?? $f->original_name ?? 'document',
                'file_size' => $f->file_size ?? $f->size ?? null,
                'mime_type' => $f->mime_type ?? null,
                'uploaded_at' => optional($f->created_at)->toIso8601String(),
                'download_url' => "/api/v1/employee/reports/{$report->id}/files/{$f->id}",
            ])->values(),
            'ocr' => $ocr,
            'ai_summary' => $aiSummary,
            'ai_validation' => $aiValidation,
            'review_history' => $reviews,
            'reviewer_feedback' => $commentFeedback->isNotEmpty() ? $commentFeedback : $reviews,
            'comments' => $commentFeedback,
            'timeline' => $timeline,
            'revision_history' => $versions,
            'versions' => $versions,
            'version_count' => max((int) ($report->version ?? 1), $versions->count() + ($versions->isEmpty() ? 0 : 0)),
        ]);
    }

    private function assertTaskOwnership(Employee $employee, int $taskId): void
    {
        $assignmentExists = TaskAssignment::where('employee_id', $employee->id)
            ->where('task_id', $taskId)
            ->exists();

        if (! $assignmentExists) {
            throw ValidationException::withMessages(['task_id' => ['You are not assigned to this task.']]);
        }
    }

    private function assertEditableDraft(Report $report): void
    {
        $status = $this->reportStatusValue($report);
        $editable = in_array($status, [ReportStatus::Draft->value, ReportStatus::RevisionRequired->value], true)
            && $status !== ReportStatus::Approved->value;

        if (! $editable || $status !== ReportStatus::Draft->value) {
            $message = $status === ReportStatus::Approved->value
                ? 'Approved reports cannot be modified.'
                : 'Only draft reports can be edited. Create a revision when revision is required.';

            throw ValidationException::withMessages(['report' => [$message]]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function reportPayload(array $data, ?Report $existing, bool $isCreate): array
    {
        $content = $data['report_description'] ?? $data['content'] ?? ($isCreate ? '' : null);
        $references = $data['references'] ?? ($isCreate ? null : null);

        $payload = [];

        if ($isCreate || array_key_exists('task_id', $data)) {
            $payload['task_id'] = $data['task_id'] ?? $existing?->task_id;
        }
        if ($isCreate || array_key_exists('title', $data)) {
            $payload['title'] = $data['title'] ?? $existing?->title;
        }
        if ($isCreate || array_key_exists('report_type', $data)) {
            $payload['report_type'] = $data['report_type'] ?? $existing?->report_type;
        }
        if ($isCreate || array_key_exists('reporting_period', $data)) {
            $payload['reporting_period'] = $data['reporting_period'] ?? $existing?->reporting_period;
        }
        if ($content !== null || $isCreate) {
            $sanitized = $this->sanitizeHtml((string) ($content ?? ''));
            $payload['content'] = $sanitized;
            $payload['description'] = $sanitized;
        }
        if ($isCreate || array_key_exists('accomplishments', $data)) {
            $payload['accomplishments'] = $data['accomplishments'] ?? $existing?->accomplishments;
        }
        if ($isCreate || array_key_exists('completed_tasks', $data) || array_key_exists('completed_tasks_summary', $data)) {
            $payload['completed_tasks_summary'] = $data['completed_tasks_summary'] ?? $data['completed_tasks'] ?? $existing?->completed_tasks_summary;
        }
        if ($isCreate || array_key_exists('challenges_encountered', $data)) {
            $payload['challenges_encountered'] = $data['challenges_encountered'] ?? $existing?->challenges_encountered;
        }
        if ($isCreate || array_key_exists('next_plans', $data)) {
            $payload['next_plans'] = $data['next_plans'] ?? $existing?->next_plans;
        }
        if ($references !== null || array_key_exists('references', $data) || $isCreate) {
            $payload['references'] = $references;
            $payload['remarks'] = $references;
        }

        return $payload;
    }

    private function assertSubmitRequirements(Report $report): void
    {
        $errors = [];

        foreach ([
            'task_id' => 'A task is required.',
            'title' => 'Report title is required.',
            'report_type' => 'Report type is required.',
            'reporting_period' => 'Reporting period is required.',
        ] as $field => $message) {
            if (blank($report->{$field})) {
                $errors[$field] = [$message];
            }
        }

        $content = trim((string) ($report->content ?? $report->description ?? ''));
        if ($content === '') {
            $errors['report_description'] = ['Report description is required.'];
        }
        if (blank($report->accomplishments)) {
            $errors['accomplishments'] = ['Accomplishments are required.'];
        }
        if (blank($report->completed_tasks_summary)) {
            $errors['completed_tasks'] = ['Completed tasks are required.'];
        }
        if (blank($report->challenges_encountered)) {
            $errors['challenges_encountered'] = ['Challenges encountered are required.'];
        }
        if (blank($report->next_plans)) {
            $errors['next_plans'] = ['Next plans are required.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    private function storeAttachments(Report $report, array $attachments, User $user): void
    {
        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store("reports/{$report->id}", 'public');

            ReportFile::create([
                'report_id' => $report->id,
                'report_version_id' => $report->versions()->latest('id')->value('id'),
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => basename($path),
                'storage_disk' => 'public',
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'file_size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath() ?: $file->path()),
                'uploaded_by' => $user->id,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateAutomaticSummary(Report $report): array
    {
        if ($this->employeeReportSummaryService === null) {
            $report->forceFill(['ai_summary_status' => 'unavailable'])->save();

            return $this->summaryUnavailableShape($report, 'AI summary service is not configured.');
        }

        $report->forceFill(['ai_summary_status' => 'generating'])->save();

        try {
            return $this->employeeReportSummaryService->generate($report, false);
        } catch (\Throwable $e) {
            $report->forceFill(['ai_summary_status' => 'failed'])->save();

            return $this->summaryUnavailableShape($report, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryUnavailableShape(Report $report, string $message): array
    {
        return [
            'report_id' => $report->id,
            'ai_summary_status' => $report->ai_summary_status ?: 'unavailable',
            'ai_summary_generated_at' => optional($report->ai_summary_generated_at)->toIso8601String(),
            'summary' => null,
            'message' => 'AI summary is unavailable. The report was still saved/submitted successfully.',
            'details' => $message,
            'regenerated' => false,
        ];
    }

    private function reportStatusValue(Report $report): string
    {
        $status = $report->status;

        if ($status instanceof ReportStatus) {
            return $status->value;
        }

        return is_string($status) ? $status : (string) $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSummary(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : ['executive_summary' => $value];
        }

        return [];
    }

    private function dispatchAiPipeline(Report $report): void
    {
        // Only dispatch if the job classes exist (graceful degradation)
        if (class_exists(ProcessReportOcrJob::class)) {
            dispatch(new ProcessReportOcrJob($report->id))->onQueue('ocr');
        }
        if (class_exists(GenerateReportSummaryJob::class)) {
            dispatch(new GenerateReportSummaryJob($report->id))->onQueue('ai');
        }
        if (class_exists(ValidateReportJob::class)) {
            dispatch(new ValidateReportJob($report->id))->onQueue('ai');
        }
    }

    private function sanitizeHtml(string $content): string
    {
        // Strip all tags except safe formatting — prevents XSS
        return strip_tags($content, '<p><br><strong><em><ul><ol><li><h2><h3><h4><blockquote>');
    }

    private function key(Employee $employee, string $segment): string
    {
        return self::CACHE_KEY_PREFIX.$segment.':'.$employee->id;
    }
}
