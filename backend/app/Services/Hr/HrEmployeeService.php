<?php

namespace App\Services\Hr;

use App\Enums\DataQualityIssueStatus;
use App\Enums\ReportStatus;
use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\DataQualityIssue;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\Report;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * HR employee management (directory detail + create/update + account status).
 *
 * Splitting this out of HrDashboardService is deliberate: the dashboard service
 * owns *read-only monitoring aggregates* and is contract-tested as such, while
 * this service *writes* employee records. Mixing the two would put mutation
 * logic behind a service whose whole value is that it cannot change anything.
 *
 * Honesty contract, identical to the dashboard service:
 *  - every task/report number is a SQL aggregate over the live tables;
 *  - a rate is `null` when its denominator is zero, never 0;
 *  - lateness uses the ONE canonical rule the dashboard uses
 *    (`HrDashboardService::lateReportCondition()`): the submission flag or a
 *    report that is demonstrably late from its own dates, so a profile and the
 *    dashboard row for that same employee can never disagree.
 *
 * Audit contract — two existing mechanisms are reused, neither duplicated:
 *  - `activity_logs` (via ActivityLogger) records the intent of each action;
 *  - `employee_histories` records the per-field before/after, which is what the
 *    profile's Activity/Audit History section reads back.
 */
class HrEmployeeService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Full HR profile for one employee, grouped into the sections the UI shows.
     *
     * @return array<string, mixed>
     */
    public function profile(Employee $employee): array
    {
        // `load`, not `loadMissing`: the write paths below touch `$employee->user`
        // before the account exists, which caches a null relation. A forced
        // reload keeps the response honest about a just-created account.
        $employee->load([
            'department:id,name',
            'position:id,name',
            'supervisor:id,employee_number,first_name,last_name',
            'user:id,name,email,username,status,last_login_at,role_id',
        ]);

        $counts = $this->counts($employee);

        return [
            'id' => $employee->id,
            'personal' => $this->personal($employee),
            'employment' => $this->employment($employee),
            'organisation' => $this->organisation($employee),
            'tasks' => $counts['tasks'],
            'reports' => $counts['reports'],
            'performance' => $this->performance($employee, $counts),
            'validation_alerts' => $this->validationAlerts($employee),
            'history' => $this->history($employee),
        ];
    }

    /**
     * Create an employee, and optionally the login account that goes with them.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Employee
    {
        $employee = DB::transaction(function () use ($data, $actor): Employee {
            $employee = Employee::create([
                'employee_number' => $data['employee_number'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'department_id' => $data['department_id'],
                'employment_status' => $data['employment_status'] ?? 'active',
                'employment_type' => $data['employment_type'] ?? 'regular',
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'date_hired' => $data['date_hired'] ?? null,
            ]);

            if (! empty($data['email'])) {
                $this->syncAccount($employee, $data, $actor, syncName: true);
            }

            return $employee;
        });

        $this->activity->log(
            activityType: 'hr.employee.create',
            description: sprintf('Created employee %s (%s).', $employee->full_name, $employee->employee_number),
            user: $actor,
            subject: $employee,
            metadata: [
                'employee_number' => $employee->employee_number,
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'employment_status' => $employee->employment_status,
            ],
        );

        return $employee;
    }

    /**
     * Update an employee record, recording every changed field in
     * `employee_histories` so the change is attributable and reversible.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, User $actor): Employee
    {
        // Resolve the linked account *before* the write so the history diff can
        // include email/account changes in the same transaction.
        $emailChanged = array_key_exists('email', $data) && filled($data['email']);
        // The account's display name is derived from the employee name, so a
        // rename has to propagate or the two drift apart.
        $nameChanged = array_key_exists('first_name', $data) || array_key_exists('last_name', $data);
        $usernameChanged = array_key_exists('username', $data) && filled($data['username']);

        DB::transaction(function () use ($employee, $data, $actor, $emailChanged, $nameChanged, $usernameChanged): void {
            $changes = $this->diff($employee, $data);

            foreach ($changes as $field => [$old, $new]) {
                $employee->forceFill([$field => $new])->save();

                EmployeeHistory::create([
                    'employee_id' => $employee->id,
                    'field_name' => $field,
                    'old_value' => $this->historyValue($old),
                    'new_value' => $this->historyValue($new),
                    'effective_date' => Carbon::today(),
                    'reason' => $data['reason'] ?? null,
                    'changed_by' => $actor->id,
                ]);
            }

            if ($employee->isDirty()) {
                $employee->save();
            }

            if ($emailChanged || $nameChanged || $usernameChanged || array_key_exists('account_status', $data)) {
                $this->syncAccount($employee, $data, $actor, syncName: $nameChanged);
            }
        });

        $this->activity->log(
            activityType: 'hr.employee.update',
            description: sprintf('Updated employee %s (%s).', $employee->full_name, $employee->employee_number),
            user: $actor,
            subject: $employee,
            metadata: ['fields' => array_keys($data)],
        );

        return $employee->refresh();
    }

    /**
     * Activate or deactivate the login account linked to an employee.
     *
     * Deactivation never deletes the account: HR needs the record and its audit
     * trail to survive, matching `employees.delete`/soft-delete semantics used
     * elsewhere in the app.
     */
    public function setAccountStatus(Employee $employee, UserStatus $status, User $actor): Employee
    {
        $user = $employee->user;

        if ($user === null) {
            // No account to change. This is a client error (404-ish), not a
            // silent success, so the caller can surface "no account exists".
            throw new \DomainException('This employee has no login account.');
        }

        $previous = $user->status;
        $user->status = $status->value;
        $user->save();

        EmployeeHistory::create([
            'employee_id' => $employee->id,
            'field_name' => 'account_status',
            'old_value' => $previous,
            'new_value' => $status->value,
            'effective_date' => Carbon::today(),
            'reason' => 'Account status changed from the HR employee directory.',
            'changed_by' => $actor->id,
        ]);

        $this->activity->log(
            activityType: "hr.employee.account.{$status->value}",
            description: sprintf(
                'Set the account for %s (%s) to %s.',
                $employee->full_name,
                $employee->employee_number,
                $status->value,
            ),
            user: $actor,
            subject: $employee,
            metadata: ['account_status' => $status->value, 'user_id' => $user->id],
        );

        return $employee->refresh();
    }

    /* ------------------------------------------------------------------ */
    /* Profile sections */
    /* ------------------------------------------------------------------ */

    /**
     * A. Personal information — identity only, straight from the record.
     *
     * @return array<string, mixed>
     */
    private function personal(Employee $employee): array
    {
        return [
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'suffix' => $employee->suffix,
            'full_name' => $employee->full_name,
            'email' => $employee->user?->email,
            'username' => $employee->user?->username,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'date_of_birth' => $employee->date_of_birth?->toDateString(),
            'profile_photo_path' => $employee->profile_photo_path,
        ];
    }

    /**
     * B. Employment information — the HR-owned facts about the engagement.
     *
     * @return array<string, mixed>
     */
    private function employment(Employee $employee): array
    {
        return [
            'employment_status' => $employee->employment_status,
            'employment_type' => $employee->employment_type,
            'date_hired' => $employee->date_hired?->toDateString(),
            'date_ended' => $employee->date_ended?->toDateString(),
            'account_status' => $employee->user?->status,
            'role' => $employee->user?->roleName(),
            'has_account' => $employee->user !== null,
            'last_login_at' => $employee->user?->last_login_at?->toDateTimeString(),
        ];
    }

    /**
     * C. Department and position.
     *
     * @return array<string, mixed>
     */
    private function organisation(Employee $employee): array
    {
        return [
            'department_id' => $employee->department_id,
            'department' => $employee->department?->name,
            'position_id' => $employee->position_id,
            'position' => $employee->position?->name,
            'supervisor_id' => $employee->supervisor_id,
            'supervisor' => $employee->supervisor
                ? trim($employee->supervisor->first_name.' '.$employee->supervisor->last_name)
                : null,
        ];
    }

    /**
     * D/E. Task and report summaries from live aggregates.
     *
     * @return array<string, array<string, mixed>>
     */
    private function counts(Employee $employee): array
    {
        $taskStatuses = TaskAssignment::query()
            ->where('employee_id', $employee->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $assigned = (int) $taskStatuses->sum();
        $completed = (int) $taskStatuses->only(HrDashboardService::ASSIGNMENT_DONE)->sum();

        $reports = Report::query()
            ->where('employee_id', $employee->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $submittedStatuses = array_values(array_diff(
            HrDashboardService::reportStatusValues(),
            [ReportStatus::Draft->value],
        ));
        $submitted = (int) $reports->only($submittedStatuses)->sum();

        $late = (int) Report::query()
            ->where('employee_id', $employee->id)
            ->whereIn('reports.id', HrDashboardService::lateReportIdsQuery())
            ->count();

        // Only the late reports that were actually handed in may reduce the
        // on-time rate: an outstanding draft is not a missed submission.
        $lateSubmitted = (int) Report::query()
            ->where('employee_id', $employee->id)
            ->whereIn('reports.id', HrDashboardService::lateReportIdsQuery('submitted'))
            ->count();

        return [
            'tasks' => [
                'total' => $assigned,
                'completed' => $completed,
                'pending' => $assigned - $completed,
                'late' => $this->lateTasks($employee),
            ],
            'reports' => [
                'total' => (int) $reports->sum(),
                'submitted' => $submitted,
                'pending' => (int) $reports->only(HrDashboardService::REPORT_PENDING)->sum(),
                'approved' => (int) $reports->get(ReportStatus::Approved->value, 0),
                'rejected' => (int) $reports->get(ReportStatus::Rejected->value, 0),
                'revision_required' => (int) $reports->get(HrDashboardService::REPORT_REVISION, 0),
                'late' => $late,
                'late_submitted' => $lateSubmitted,
            ],
        ];
    }

    /**
     * F. Performance summary — rates are null when there is nothing to divide.
     *
     * @param  array<string, array<string, mixed>>  $counts
     * @return array<string, mixed>
     */
    private function performance(Employee $employee, array $counts): array
    {
        $tasks = $counts['tasks'];
        $reports = $counts['reports'];
        $onTime = $reports['submitted'] - $reports['late_submitted'];

        return [
            'completion_rate' => $this->rate($tasks['completed'], $tasks['total']),
            'on_time_submission_rate' => $this->rate($onTime, $reports['submitted']),
            'approval_rate' => $this->rate($reports['approved'], $reports['submitted']),
            'late_tasks' => $tasks['late'],
            'late_reports' => $reports['late'],
            'status' => $employee->employment_status,
        ];
    }

    /**
     * G. Data validation alerts — the existing DataQualityIssue pipeline,
     * scoped to this employee. Read-only here: resolution stays on the existing
     * data-quality endpoints so there is exactly one review workflow.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validationAlerts(Employee $employee): array
    {
        $open = self::openAlertStatuses();

        return DataQualityIssue::query()
            ->where('employee_id', $employee->id)
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (DataQualityIssue $issue): array => [
                'id' => $issue->id,
                'issue_type' => $issue->issue_type?->value,
                'severity' => $issue->severity?->value,
                'status' => $issue->status?->value,
                // The UI emphasises unresolved alerts; resolved ones are still
                // shown so the record of what was found is not lost.
                'is_open' => in_array($issue->status?->value, $open, true),
                'title' => $issue->title,
                'description' => $issue->description,
                'detected_value' => $issue->detected_value,
                'reviewed_at' => $issue->reviewed_at?->toDateTimeString(),
                'created_at' => $issue->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * H. Activity/audit history: `employee_histories` (per-field changes) plus
     * the matching `activity_logs` entries (who did what and when).
     *
     * @return array<int, array<string, mixed>>
     */
    private function history(Employee $employee): array
    {
        $entries = EmployeeHistory::query()
            ->where('employee_id', $employee->id)
            ->with('changedBy:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (EmployeeHistory $history): array => [
                'source' => 'employee_histories',
                'id' => $history->id,
                'event' => $history->field_name,
                'description' => sprintf(
                    '%s: %s → %s',
                    $history->field_name,
                    $this->historyValue($history->old_value) ?? '—',
                    $this->historyValue($history->new_value) ?? '—',
                ),
                'actor' => $history->changedBy?->name,
                'occurred_at' => $history->effective_date?->toDateString(),
            ]);

        $activity = ActivityLog::query()
            ->where('subject_type', Employee::class)
            ->where('subject_id', $employee->id)
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'source' => 'activity_logs',
                'id' => $log->id,
                'event' => $log->activity_type,
                'description' => $log->description,
                'actor' => $log->user?->name,
                'occurred_at' => $log->created_at?->toDateTimeString(),
            ]);

        return $entries
            ->concat($activity)
            ->sortByDesc('occurred_at')
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Internals */
    /* ------------------------------------------------------------------ */

    /**
     * Find employees whose normalised first/middle/last name matches.
     *
     * A duplicate name is a warning, not a hard block: real institutions do
     * employ two people with the same name, so the caller decides whether to
     * proceed. The comparison is case-insensitive, whitespace-collapsed and
     * ignores the suffix.
     *
     * @return array<int, array<string, mixed>>
     */
    public function duplicateNames(
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?int $excludeId = null,
    ): array {
        $normalized = self::normalizeName($firstName, $middleName, $lastName);

        if ($normalized === '') {
            return [];
        }

        return Employee::query()
            ->with(['department:id,name', 'user:id,email,username'])
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->get([
                'id', 'employee_number', 'first_name', 'middle_name', 'last_name',
                'suffix', 'department_id', 'user_id',
            ])
            ->filter(fn (Employee $employee): bool => self::normalizeName(
                $employee->first_name,
                $employee->middle_name,
                $employee->last_name,
            ) === $normalized)
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'email' => $employee->user?->email,
                'username' => $employee->user?->username,
            ])
            ->values()
            ->all();
    }

    /**
     * Canonical form used for duplicate-name comparison.
     */
    public static function normalizeName(?string ...$parts): string
    {
        $values = array_filter(
            array_map(fn (?string $part): string => Str::squish((string) $part), $parts),
            fn (string $part): bool => $part !== '',
        );

        return Str::lower(implode(' ', $values));
    }

    /**
     * Resolve the fields that actually changed, so `employee_histories` never
     * records a no-op write.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diff(Employee $employee, array $data): array
    {
        $fields = [
            'employee_number',
            'first_name', 'middle_name', 'last_name', 'suffix',
            'position_id', 'department_id', 'employment_status', 'employment_type',
            'date_of_birth', 'phone', 'address', 'supervisor_id',
            'date_hired', 'date_ended',
        ];

        $changes = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $new = $data[$field];
            $old = $employee->{$field};

            // Dates compare as Carbon on both sides once cast, so compare the
            // date strings the form actually submits.
            if ($old instanceof \DateTimeInterface) {
                $old = $old->format('Y-m-d');
            }
            if ($new instanceof \DateTimeInterface) {
                $new = $new->format('Y-m-d');
            }

            if ((string) $old !== (string) $new) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }

    /**
     * Create or update the login account paired with an employee record.
     *
     * Reuses the existing `users` table and the same role/permission plumbing
     * `AdminUserManagementService` uses; no parallel identity store.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncAccount(Employee $employee, array $data, User $actor, bool $syncName): void
    {
        $user = $employee->user;

        // An edit that only changes, say, the phone must not blank the account's
        // email, and must not fail on an absent key.
        $email = $data['email'] ?? $user?->email;
        $status = $data['account_status'] ?? $user?->status ?? UserStatus::Active->value;

        if ($user === null) {
            if (blank($email)) {
                throw ValidationException::withMessages([
                    'email' => 'An email address is required to create a login account for this employee.',
                ]);
            }

            $password = $data['password'] ?? null;

            $username = $data['username'] ?? null;

            $user = User::create([
                'name' => $employee->full_name,
                'email' => $email,
                'username' => filled($username) ? $username : User::generateUsername((string) $email),
                // A password is never invented: without an explicit one the
                // account is created without a usable credential and the
                // response still tells the operator to set one.
                'password' => Hash::make($password ?? Str::random(40)),
                'status' => $status,
                'email_verified_at' => Carbon::now(),
            ]);

            // An account with no role cannot log in and see anything. Every
            // employee account gets the `employee` role, which is also what the
            // existing seeded employee accounts hold; Admin can promote it
            // afterwards through the existing RBAC screens.
            $user->assignRole(RoleEnum::Employee);

            $employee->forceFill(['user_id' => $user->id])->save();

            $this->activity->log(
                activityType: 'hr.employee.account.create',
                description: sprintf('Created a login account for %s.', $employee->full_name),
                user: $actor,
                subject: $employee,
                metadata: [
                    'email' => $email,
                    'role' => RoleEnum::Employee->value,
                    'needs_password' => $password === null,
                ],
            );

            return;
        }

        $updates = [];

        if ($email !== $user->email) {
            $updates['email'] = $email;
        }

        if (filled($data['username'] ?? null) && $data['username'] !== $user->username) {
            $updates['username'] = $data['username'];
        }

        if ($status !== $user->status) {
            $updates['status'] = $status;

            EmployeeHistory::create([
                'employee_id' => $employee->id,
                'field_name' => 'account_status',
                'old_value' => $user->status,
                'new_value' => $status,
                'effective_date' => Carbon::today(),
                'reason' => 'Account status changed while editing the employee record.',
                'changed_by' => $actor->id,
            ]);
        }

        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        if ($syncName) {
            $updates['name'] = $employee->full_name;
        }

        if ($updates !== []) {
            $user->fill($updates)->save();
        }
    }

    /**
     * Late tasks reuse the dashboard's canonical effective-deadline rule rather
     * than inventing a second definition.
     *
     * `task_assignments.due_date` wins, falling back to the parent task's
     * deadline; `tasks` is LEFT JOINed because `task_id` is nullable and a
     * missing parent row must not silently drop the assignment from the count.
     */
    private function lateTasks(Employee $employee): int
    {
        return (int) HrDashboardService::applyOverdueAssignmentRule(
            $employee->taskAssignments()
                ->toBase()
                ->leftJoin('tasks', 'tasks.id', '=', 'task_assignments.task_id')
        )->count();
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    private function historyValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * Data-quality statuses that still need a human decision.
     *
     * @return array<int, string>
     */
    public static function openAlertStatuses(): array
    {
        return [
            DataQualityIssueStatus::OPEN->value,
            DataQualityIssueStatus::UNDER_REVIEW->value,
            DataQualityIssueStatus::CONFIRMED->value,
        ];
    }
}
