<?php

namespace App\Services\Employee;

use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\EmployeeTargetAdjustmentRequest;
use App\Models\EmployeeTargetBreakdown;
use App\Models\EmployeeTargetMilestone;
use App\Models\EmployeeTargetNote;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * My Targets — employee-facing target workspace.
 *
 * Owns the five target enhancements without touching the existing target chain:
 *  1. Breakdown   — sub-targets with individual progress, rolled up into the
 *                   parent target (weighted by target_value, else equally).
 *  2. Milestones  — checkpoints auto-marked achieved from real progress, with
 *                   the completion moment persisted in `achieved_at`.
 *  3. Adjustments — employees request a change with a reason; an administrator
 *                   approves or rejects it. Approval applies the change and
 *                   stores the pre-change values in `original_snapshot`.
 *  4. Notes       — author-only achievement notes per target.
 *  5. Suggestions — advisory, deterministic recommendations derived strictly
 *                   from the employee's own targets and their recorded
 *                   progress. Never a report and never an unrelated task.
 *
 * HOW PROGRESS IS DERIVED
 * `employee_targets` has no `actual_value` column. This service mirrors the
 * canonical rule used by AdminKpiTargetService: when a target has no
 * sub-targets, progress is the share of its linked tasks that reached a
 * completed state; when it does have sub-targets, progress is their weighted
 * average. A target with neither is reported as `null` ("no data"), never a
 * fabricated 0%.
 *
 * SECURITY: the employee is always resolved from the authenticated user. No
 * `employee_id` is accepted from request input, and a target/sub-target that
 * does not belong to the caller is answered as 404 (existence is not leaked).
 */
class EmployeeTargetService
{
    public const TASK_COMPLETED = 'completed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_MISSED = 'missed';

    public const STATUS_NOT_STARTED = 'not_started';

    /** Days before the deadline that count as "deadline approaching". */
    private const SOON_DAYS = 14;

    /** Advisory disclaimer attached to every suggestion payload. */
    private const SUGGESTION_DISCLAIMER = 'These suggestions are advisory and generated only from your assigned targets and their recorded progress. They do not change targets, approve adjustments, or make final decisions.';

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Listing / detail
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The enriched target payload for the signed-in employee.
     *
     * @return array<string, mixed>
     */
    public function listForEmployee(User $user): array
    {
        return $this->payloadForEmployee($this->requireEmployee($user));
    }

    /**
     * The enriched target payload for a concrete employee. Reused by the
     * employee dashboard aggregation so progress is computed in ONE place.
     *
     * @return array<string, mixed>
     */
    public function payloadForEmployee(Employee $employee): array
    {
        $targets = EmployeeTarget::query()
            ->where('employee_id', $employee->id)
            ->with(['breakdowns', 'milestones', 'tasks:id,employee_target_id,status'])
            ->withCount([
                'notes',
                'adjustmentRequests as pending_adjustments_count' => fn (Builder $query) => $query->where('status', EmployeeTargetAdjustmentRequest::STATUS_PENDING),
            ])
            ->orderByDesc('id')
            ->get();

        $items = $targets->map(fn (EmployeeTarget $target) => $this->presentTarget($target))->values();

        $completed = $items->where('derived_status', self::STATUS_COMPLETED)->count();

        return [
            'items' => $items,
            'targets' => $items,
            'summary' => [
                'total' => $items->count(),
                'completed' => $completed,
                'pending' => $items->count() - $completed,
            ],
            'annual' => $this->annualTarget($targets),
        ];
    }

    /**
     * A single target with its breakdowns, milestones, notes and requests.
     *
     * @return array<string, mixed>
     */
    public function employeeTargetDetail(User $user, int $targetId): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);

        // Progress may have moved since the last write (linked tasks), so the
        // milestone checkpoints are reconciled on read.
        $this->syncMilestones($target);

        return $this->presentDetail($target);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Progress engine
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Derive the effective progress of a target from real evidence.
     *
     * @return array{progress: float|null, source: string, breakdowns_total: int, breakdowns_completed: int, linked_tasks_total: int, linked_tasks_completed: int}
     */
    public function progress(EmployeeTarget $target): array
    {
        $target->loadMissing(['breakdowns', 'tasks']);

        $breakdowns = $target->breakdowns;
        $tasks = $target->tasks;

        $tasksTotal = $tasks->count();
        $tasksCompleted = $tasks->where('status', self::TASK_COMPLETED)->count();

        if ($breakdowns->isNotEmpty()) {
            // Weight by target_value when it is set, otherwise weight equally.
            $weight = (float) $breakdowns->sum(fn (EmployeeTargetBreakdown $b) => (float) ($b->target_value ?? 1));
            $weighted = (float) $breakdowns->sum(fn (EmployeeTargetBreakdown $b) => ((float) ($b->target_value ?? 1)) * (int) $b->progress);
            $pct = $weight > 0 ? round($weighted / $weight, 2) : null;

            return [
                'progress' => $pct,
                'source' => 'breakdowns',
                'breakdowns_total' => $breakdowns->count(),
                'breakdowns_completed' => $breakdowns->where('status', EmployeeTargetBreakdown::STATUS_COMPLETED)->count(),
                'linked_tasks_total' => $tasksTotal,
                'linked_tasks_completed' => $tasksCompleted,
            ];
        }

        $pct = $tasksTotal > 0 ? round(($tasksCompleted / $tasksTotal) * 100, 2) : null;

        return [
            'progress' => $pct,
            'source' => $tasksTotal > 0 ? 'tasks' : 'none',
            'breakdowns_total' => 0,
            'breakdowns_completed' => 0,
            'linked_tasks_total' => $tasksTotal,
            'linked_tasks_completed' => $tasksCompleted,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Target breakdown (sub-targets)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function breakdowns(User $user, int $targetId): array
    {
        $target = $this->findTargetFor($this->requireEmployee($user), $targetId);

        return $target->breakdowns()->ordered()->get()
            ->map(fn (EmployeeTargetBreakdown $b) => $this->presentBreakdown($b))->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createBreakdown(User $user, int $targetId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);

        $breakdown = DB::transaction(function () use ($target, $data, $user): EmployeeTargetBreakdown {
            $breakdown = $target->breakdowns()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'target_value' => $data['target_value'] ?? null,
                'target_unit' => $data['target_unit'] ?? $target->target_unit,
                'progress' => (int) ($data['progress'] ?? 0),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->syncBreakdownProgress($breakdown);
            $this->syncTargetFromBreakdowns($target);
            $this->syncMilestones($target);

            $this->activityLogger->log(
                'target.breakdown.created',
                'Added a sub-target to a target.',
                $user,
                $target,
                ['breakdown_id' => $breakdown->id, 'title' => $breakdown->title],
            );

            return $breakdown;
        });

        return $this->presentBreakdown($breakdown->fresh());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateBreakdown(User $user, int $targetId, int $breakdownId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);
        $breakdown = $this->findBreakdownFor($target, $breakdownId);

        DB::transaction(function () use ($target, $breakdown, $data, $user): void {
            $breakdown->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'target_value' => $data['target_value'] ?? null,
                'target_unit' => $data['target_unit'] ?? null,
                'progress' => array_key_exists('progress', $data) ? (int) $data['progress'] : null,
                'sort_order' => $data['sort_order'] ?? null,
            ], fn ($value) => $value !== null));

            $breakdown->save();

            $this->syncBreakdownProgress($breakdown);
            $this->syncTargetFromBreakdowns($target);
            $this->syncMilestones($target);

            $this->activityLogger->log(
                'target.breakdown.updated',
                'Updated a sub-target.',
                $user,
                $target,
                ['breakdown_id' => $breakdown->id, 'progress' => (int) $breakdown->progress],
            );
        });

        return $this->presentBreakdown($breakdown->fresh());
    }

    public function deleteBreakdown(User $user, int $targetId, int $breakdownId): void
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);
        $breakdown = $this->findBreakdownFor($target, $breakdownId);

        DB::transaction(function () use ($target, $breakdown, $user): void {
            $breakdownId = $breakdown->id;
            $breakdown->delete();

            $this->syncTargetFromBreakdowns($target);
            $this->syncMilestones($target);

            $this->activityLogger->log(
                'target.breakdown.deleted',
                'Removed a sub-target.',
                $user,
                $target,
                ['breakdown_id' => $breakdownId],
            );
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Milestones
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function milestones(User $user, int $targetId): array
    {
        $target = $this->findTargetFor($this->requireEmployee($user), $targetId);
        $this->syncMilestones($target);

        return $target->milestones()->ordered()->get()
            ->map(fn (EmployeeTargetMilestone $m) => $this->presentMilestone($m))->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createMilestone(User $user, int $targetId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);

        $milestone = DB::transaction(function () use ($target, $data, $user): EmployeeTargetMilestone {
            $milestone = $target->milestones()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'threshold_percent' => (int) $data['threshold_percent'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->syncMilestones($target);

            $this->activityLogger->log(
                'target.milestone.created',
                'Added a milestone checkpoint.',
                $user,
                $target,
                ['milestone_id' => $milestone->id, 'threshold_percent' => (int) $milestone->threshold_percent],
            );

            return $milestone;
        });

        return $this->presentMilestone($milestone->fresh());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateMilestone(User $user, int $targetId, int $milestoneId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);
        $milestone = $this->findMilestoneFor($target, $milestoneId);

        DB::transaction(function () use ($target, $milestone, $data, $user): void {
            $milestone->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'threshold_percent' => array_key_exists('threshold_percent', $data) ? (int) $data['threshold_percent'] : null,
                'sort_order' => $data['sort_order'] ?? null,
            ], fn ($value) => $value !== null));

            $milestone->save();

            $this->syncMilestones($target);

            $this->activityLogger->log(
                'target.milestone.updated',
                'Updated a milestone checkpoint.',
                $user,
                $target,
                ['milestone_id' => $milestone->id],
            );
        });

        return $this->presentMilestone($milestone->fresh());
    }

    public function deleteMilestone(User $user, int $targetId, int $milestoneId): void
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);
        $milestone = $this->findMilestoneFor($target, $milestoneId);

        DB::transaction(function () use ($target, $milestone, $user): void {
            $id = $milestone->id;
            $milestone->delete();

            $this->activityLogger->log(
                'target.milestone.deleted',
                'Removed a milestone checkpoint.',
                $user,
                $target,
                ['milestone_id' => $id],
            );
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Adjustment requests (employee)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function adjustments(User $user, int $targetId): array
    {
        $target = $this->findTargetFor($this->requireEmployee($user), $targetId);

        return $target->adjustmentRequests()->with('reviewedBy:id,name')->get()
            ->map(fn (EmployeeTargetAdjustmentRequest $r) => $this->presentAdjustment($r))->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createAdjustment(User $user, int $targetId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);

        $request = DB::transaction(function () use ($target, $employee, $user, $data): EmployeeTargetAdjustmentRequest {
            $request = $target->adjustmentRequests()->create([
                'employee_id' => $employee->id,
                'requested_by' => $user->id,
                'reason' => $data['reason'],
                'request_type' => $data['request_type'] ?? 'other',
                'proposed_target_value' => $data['proposed_target_value'] ?? null,
                'proposed_target_unit' => $data['proposed_target_unit'] ?? null,
                'proposed_start_date' => $data['proposed_start_date'] ?? null,
                'proposed_end_date' => $data['proposed_end_date'] ?? null,
                'proposed_description' => $data['proposed_description'] ?? null,
                'status' => EmployeeTargetAdjustmentRequest::STATUS_PENDING,
            ]);

            $this->activityLogger->log(
                'target.adjustment.requested',
                'Submitted a target adjustment request.',
                $user,
                $target,
                ['adjustment_request_id' => $request->id, 'request_type' => $request->request_type],
            );

            return $request;
        });

        return $this->presentAdjustment($request);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Adjustment requests (admin review)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Paginated adjustment requests for the review queue.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdjustments(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return EmployeeTargetAdjustmentRequest::query()
            ->with([
                'target:id,employee_id,title,description,target_value,target_unit,start_date,end_date,status',
                'employee:id,first_name,middle_name,last_name,employee_number,department_id',
                'employee.department:id,name',
                'requestedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->when(
                ! empty($filters['status']) && in_array($filters['status'], [
                    EmployeeTargetAdjustmentRequest::STATUS_PENDING,
                    EmployeeTargetAdjustmentRequest::STATUS_APPROVED,
                    EmployeeTargetAdjustmentRequest::STATUS_REJECTED,
                ], true),
                fn (Builder $q) => $q->where('status', $filters['status']),
            )
            ->when(
                ! empty($filters['search']),
                function (Builder $q) use ($filters) {
                    $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                    $q->where(function (Builder $inner) use ($term) {
                        $inner->where('reason', 'like', $term)
                            ->orWhereHas('target', fn (Builder $t) => $t->where('title', 'like', $term));
                    });
                },
            )
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (EmployeeTargetAdjustmentRequest $r) => $this->presentAdjustment($r));
    }

    /**
     * @return array<string, mixed>
     */
    public function adjustmentSummary(): array
    {
        $base = EmployeeTargetAdjustmentRequest::query();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', EmployeeTargetAdjustmentRequest::STATUS_PENDING)->count(),
            'approved' => (clone $base)->where('status', EmployeeTargetAdjustmentRequest::STATUS_APPROVED)->count(),
            'rejected' => (clone $base)->where('status', EmployeeTargetAdjustmentRequest::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function adjustmentDetail(int $id): ?array
    {
        $request = EmployeeTargetAdjustmentRequest::query()
            ->with([
                'target.breakdowns',
                'target.milestones',
                'target.notes.employee:id,first_name,middle_name,last_name',
                'employee:id,first_name,middle_name,last_name,employee_number,department_id',
                'employee.department:id,name',
                'requestedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->find($id);

        if ($request === null) {
            return null;
        }

        $detail = $this->presentAdjustment($request);
        $detail['target'] = $request->target ? $this->presentTarget($request->target) : null;
        $detail['target_detail'] = $request->target ? [
            'breakdowns' => $request->target->breakdowns->map(fn (EmployeeTargetBreakdown $b) => $this->presentBreakdown($b))->values(),
            'milestones' => $request->target->milestones->map(fn (EmployeeTargetMilestone $m) => $this->presentMilestone($m))->values(),
            'notes' => $request->target->notes->map(fn (EmployeeTargetNote $n) => $this->presentNote($n))->values(),
        ] : null;

        return $detail;
    }

    /**
     * Approve a request: snapshot the original target, apply the approved
     * change, and record the decision. History is appended to activity_logs.
     *
     * @return array<string, mixed>
     */
    public function approveAdjustment(User $admin, int $id, ?string $note): array
    {
        $request = $this->findAdjustment($id);
        $this->assertPending($request);

        DB::transaction(function () use ($request, $admin, $note): void {
            $target = $request->target ?? EmployeeTarget::find($request->employee_target_id);

            if ($target === null) {
                throw ValidationException::withMessages(['target' => ['The target no longer exists.']]);
            }

            // Preserve the original target values exactly once, before applying.
            $snapshot = $request->original_snapshot ?? $this->snapshotTarget($target);
            $updates = $this->approvedUpdates($request);

            if ($updates !== []) {
                $target->update($updates);
            }

            $request->forceFill([
                'status' => EmployeeTargetAdjustmentRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => Carbon::now(),
                'decision_note' => $note,
                'original_snapshot' => $snapshot,
                'applied_at' => $updates !== [] ? Carbon::now() : null,
            ])->save();

            $this->activityLogger->log(
                'target.adjustment.approved',
                'Approved a target adjustment request.',
                $admin,
                $target,
                [
                    'adjustment_request_id' => $request->id,
                    'applied_fields' => array_keys($updates),
                    'original_snapshot' => $snapshot,
                ],
            );
        });

        return $this->presentAdjustment($request->fresh(['target', 'reviewedBy']));
    }

    /**
     * Reject a request, recording the decision without touching the target.
     *
     * @return array<string, mixed>
     */
    public function rejectAdjustment(User $admin, int $id, ?string $note): array
    {
        $request = $this->findAdjustment($id);
        $this->assertPending($request);

        DB::transaction(function () use ($request, $admin, $note): void {
            $request->forceFill([
                'status' => EmployeeTargetAdjustmentRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => Carbon::now(),
                'decision_note' => $note,
            ])->save();

            $this->activityLogger->log(
                'target.adjustment.rejected',
                'Rejected a target adjustment request.',
                $admin,
                $request->target,
                ['adjustment_request_id' => $request->id],
            );
        });

        return $this->presentAdjustment($request->fresh(['target', 'reviewedBy']));
    }

    /**
     * A target plus its breakdowns, milestones and notes for admin review.
     *
     * @return array<string, mixed>|null
     */
    public function adminTargetDetail(int $targetId): ?array
    {
        $target = EmployeeTarget::query()
            ->with([
                'employee:id,first_name,middle_name,last_name,employee_number,department_id',
                'employee.department:id,name',
                'breakdowns',
                'milestones',
                'notes.employee:id,first_name,middle_name,last_name',
                'tasks:id,employee_target_id,status',
                'adjustmentRequests.reviewedBy:id,name',
            ])
            ->find($targetId);

        if ($target === null) {
            return null;
        }

        $detail = $this->presentDetail($target);
        $detail['target']['employee_name'] = $target->employee?->full_name;
        $detail['target']['employee_number'] = $target->employee?->employee_number;
        $detail['target']['department_name'] = $target->employee?->department?->name;
        $detail['adjustments'] = $target->adjustmentRequests
            ->map(fn (EmployeeTargetAdjustmentRequest $r) => $this->presentAdjustment($r))->values();

        return $detail;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Achievement notes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function notes(User $user, int $targetId): array
    {
        $target = $this->findTargetFor($this->requireEmployee($user), $targetId);

        return $target->notes()->get()
            ->map(fn (EmployeeTargetNote $n) => $this->presentNote($n))->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createNote(User $user, int $targetId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);

        $note = DB::transaction(function () use ($target, $employee, $user, $data): EmployeeTargetNote {
            $note = $target->notes()->create([
                'employee_id' => $employee->id,
                'body' => $data['body'],
            ]);

            $this->activityLogger->log(
                'target.note.created',
                'Added a target achievement note.',
                $user,
                $target,
                ['note_id' => $note->id],
            );

            return $note;
        });

        return $this->presentNote($note);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateNote(User $user, int $targetId, int $noteId, array $data): array
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);
        $note = $this->findNoteFor($target, $noteId);

        $this->assertOwnsNote($employee, $note);

        DB::transaction(function () use ($note, $target, $user, $data): void {
            $note->update(['body' => $data['body']]);

            $this->activityLogger->log(
                'target.note.updated',
                'Updated a target achievement note.',
                $user,
                $target,
                ['note_id' => $note->id],
            );
        });

        return $this->presentNote($note->fresh());
    }

    public function deleteNote(User $user, int $targetId, int $noteId): void
    {
        $employee = $this->requireEmployee($user);
        $target = $this->findTargetFor($employee, $targetId);
        $note = $this->findNoteFor($target, $noteId);

        $this->assertOwnsNote($employee, $note);

        DB::transaction(function () use ($note, $target, $user): void {
            $id = $note->id;
            $note->delete();

            $this->activityLogger->log(
                'target.note.deleted',
                'Removed a target achievement note.',
                $user,
                $target,
                ['note_id' => $id],
            );
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AI target suggestions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Advisory suggestions derived strictly from the employee's own targets.
     * Never generates a report and never performs an unrelated AI task.
     *
     * @return array<string, mixed>
     */
    public function suggestions(User $user): array
    {
        $employee = $this->requireEmployee($user);

        $targets = EmployeeTarget::query()
            ->where('employee_id', $employee->id)
            ->with(['breakdowns', 'milestones', 'tasks:id,employee_target_id,status'])
            ->orderByDesc('id')
            ->get();

        $suggestions = [];

        foreach ($targets as $target) {
            foreach ($this->targetSuggestions($target) as $suggestion) {
                $suggestions[] = $suggestion;
            }
        }

        return [
            'available' => $targets->isNotEmpty(),
            'targets_analyzed' => $targets->count(),
            'suggestions' => $suggestions,
            'generated_at' => Carbon::now()->toIso8601String(),
            'disclaimer' => self::SUGGESTION_DISCLAIMER,
            'no_data_reason' => $targets->isEmpty()
                ? 'No targets are assigned to you yet, so there is nothing to analyse.'
                : null,
        ];
    }

    /**
     * Deterministic suggestions for a single target.
     *
     * @return list<array<string, mixed>>
     */
    private function targetSuggestions(EmployeeTarget $target): array
    {
        $evidence = $this->progress($target);
        $pct = $evidence['progress'];
        $title = $target->title ?? 'Untitled target';
        $out = [];

        if ($pct === null) {
            $out[] = $this->suggestion(
                $target,
                'no_progress_data',
                'No measurable progress yet',
                'This target has no sub-targets or linked tasks, so its progress cannot be measured. Break it into sub-targets or link tasks so progress is tracked.',
                'medium',
                ['target_title' => $title],
            );

            return $out;
        }

        if ($pct >= 100) {
            return $out; // Already achieved — nothing to act on.
        }

        $daysLeft = $target->end_date !== null
            ? (int) Carbon::now()->startOfDay()->diffInDays($target->end_date->copy()->startOfDay(), false)
            : null;

        if ($daysLeft !== null && $daysLeft < 0) {
            $out[] = $this->suggestion(
                $target,
                'overdue',
                'Target deadline has passed',
                "The deadline ({$target->end_date->toDateString()}) has passed while progress is {$pct}%. Prioritise the remaining work, or submit an adjustment request if the timeline is no longer realistic.",
                'high',
                ['target_title' => $title, 'progress' => $pct, 'days_overdue' => abs($daysLeft)],
            );
        } elseif ($daysLeft !== null && $daysLeft <= self::SOON_DAYS) {
            $out[] = $this->suggestion(
                $target,
                'deadline_soon',
                'Deadline approaching',
                "Only {$daysLeft} day(s) remain and progress is {$pct}%. Focus your remaining effort on this target to reach 100%.",
                'high',
                ['target_title' => $title, 'progress' => $pct, 'days_remaining' => $daysLeft],
            );
        }

        if ($evidence['source'] === 'tasks') {
            $out[] = $this->suggestion(
                $target,
                'add_breakdown',
                'Break this target down',
                "Progress is tracked from {$evidence['linked_tasks_total']} linked task(s) at {$pct}%. Splitting it into sub-targets makes individual progress easier to manage and keeps daily work connected to the target.",
                'low',
                ['target_title' => $title, 'progress' => $pct, 'linked_tasks_total' => $evidence['linked_tasks_total']],
            );
        }

        if ($evidence['source'] === 'breakdowns' && $evidence['breakdowns_total'] > 0) {
            $lowest = $target->breakdowns->sortBy('progress')->first();

            if ($lowest !== null && (int) $lowest->progress < 100) {
                $out[] = $this->suggestion(
                    $target,
                    'lagging_breakdown',
                    'Sub-target needs attention',
                    "The sub-target \"{$lowest->title}\" is at {$lowest->progress}%. Prioritise it to lift the overall target progress.",
                    'medium',
                    ['target_title' => $title, 'breakdown_id' => $lowest->id, 'breakdown_progress' => (int) $lowest->progress],
                );
            }
        }

        $next = $target->milestones->where('status', '!=', EmployeeTargetMilestone::STATUS_ACHIEVED)
            ->sortBy('threshold_percent')->first();

        if ($next !== null) {
            $out[] = $this->suggestion(
                $target,
                'next_milestone',
                'Next milestone checkpoint',
                "The next milestone is \"{$next->title}\" at {$next->threshold_percent}%. Current progress is {$pct}%.",
                'low',
                ['target_title' => $title, 'milestone_id' => $next->id, 'threshold_percent' => (int) $next->threshold_percent, 'progress' => $pct],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function suggestion(EmployeeTarget $target, string $key, string $title, string $detail, string $severity, array $evidence): array
    {
        return [
            'key' => $key,
            'target_id' => $target->id,
            'target_title' => $target->title,
            'title' => $title,
            'detail' => $detail,
            'severity' => $severity,
            'evidence' => $evidence,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal sync helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recompute a sub-target's stored status/achieved moment from its progress.
     */
    private function syncBreakdownProgress(EmployeeTargetBreakdown $breakdown): void
    {
        $progress = max(0, min(100, (int) $breakdown->progress));

        $status = match (true) {
            $progress >= 100 => EmployeeTargetBreakdown::STATUS_COMPLETED,
            $progress > 0 => EmployeeTargetBreakdown::STATUS_IN_PROGRESS,
            default => EmployeeTargetBreakdown::STATUS_PENDING,
        };

        $breakdown->forceFill([
            'progress' => $progress,
            'status' => $status,
            'completed_at' => $progress >= 100 ? ($breakdown->completed_at ?? Carbon::now()) : null,
        ])->save();
    }

    /**
     * Connect sub-target progress back to the parent target's stored status.
     */
    private function syncTargetFromBreakdowns(EmployeeTarget $target): void
    {
        $target->refresh()->load('breakdowns');

        if ($target->breakdowns->isEmpty()) {
            return;
        }

        $progress = $this->progress($target)['progress'];

        if ($progress === null) {
            return;
        }

        if ($progress >= 100 && $target->status !== self::STATUS_COMPLETED) {
            $target->update(['status' => self::STATUS_COMPLETED]);
        } elseif ($progress < 100 && $target->status === self::STATUS_COMPLETED) {
            $target->update(['status' => 'active']);
        }
    }

    /**
     * Automatically mark milestones achieved/pending from real progress,
     * recording the moment each threshold was first reached.
     */
    private function syncMilestones(EmployeeTarget $target): void
    {
        $target->refresh()->load('milestones');

        if ($target->milestones->isEmpty()) {
            return;
        }

        $progress = $this->progress($target)['progress'];

        foreach ($target->milestones as $milestone) {
            $reached = $progress !== null && $progress >= (int) $milestone->threshold_percent;

            if ($reached && $milestone->status !== EmployeeTargetMilestone::STATUS_ACHIEVED) {
                $milestone->forceFill([
                    'status' => EmployeeTargetMilestone::STATUS_ACHIEVED,
                    'achieved_at' => $milestone->achieved_at ?? Carbon::now(),
                ])->save();
            } elseif (! $reached && $milestone->status === EmployeeTargetMilestone::STATUS_ACHIEVED) {
                $milestone->forceFill([
                    'status' => EmployeeTargetMilestone::STATUS_PENDING,
                    'achieved_at' => null,
                ])->save();
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Presentation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function presentTarget(EmployeeTarget $target): array
    {
        $evidence = $this->progress($target);
        $pct = $evidence['progress'];

        return [
            'id' => $target->id,
            'title' => $target->title,
            'description' => $target->description,
            // Legacy-compatible fields consumed by the dashboard widget.
            'metric' => $target->target_unit,
            'target_value' => $target->target_value === null ? null : (float) $target->target_value,
            'actual_value' => $evidence['source'] === 'breakdowns'
                ? $evidence['breakdowns_completed']
                : $evidence['linked_tasks_completed'],
            'target_unit' => $target->target_unit,
            'progress' => $pct,
            'status' => $pct !== null && $pct >= 100 ? 'achieved' : 'in_progress',
            'derived_status' => $this->derivedStatus($target, $pct),
            'progress_source' => $evidence['source'],
            'start_date' => $target->start_date?->toDateString(),
            'end_date' => $target->end_date?->toDateString(),
            'breakdowns_count' => $evidence['breakdowns_total'],
            'breakdowns_completed' => $evidence['breakdowns_completed'],
            'milestones_count' => $target->milestones->count(),
            'milestones_achieved' => $target->milestones->where('status', EmployeeTargetMilestone::STATUS_ACHIEVED)->count(),
            'tasks_total' => $evidence['linked_tasks_total'],
            'tasks_completed' => $evidence['linked_tasks_completed'],
            'notes_count' => (int) ($target->notes_count ?? $target->notes->count()),
            'pending_adjustments' => (int) ($target->pending_adjustments_count ?? 0),
        ];
    }

    private function derivedStatus(EmployeeTarget $target, ?float $pct): string
    {
        if ($pct !== null && $pct >= 100) {
            return self::STATUS_COMPLETED;
        }

        if ($pct === null) {
            return $target->status === self::STATUS_COMPLETED ? self::STATUS_COMPLETED : self::STATUS_NOT_STARTED;
        }

        if ($target->end_date !== null && $target->end_date->isPast()) {
            return self::STATUS_MISSED;
        }

        return self::STATUS_IN_PROGRESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetail(EmployeeTarget $target): array
    {
        $target->loadMissing(['breakdowns', 'milestones', 'notes', 'tasks']);

        return [
            'target' => $this->presentTarget($target),
            'breakdowns' => $target->breakdowns->map(fn (EmployeeTargetBreakdown $b) => $this->presentBreakdown($b))->values(),
            'milestones' => $target->milestones->map(fn (EmployeeTargetMilestone $m) => $this->presentMilestone($m))->values(),
            'notes' => $target->notes->map(fn (EmployeeTargetNote $n) => $this->presentNote($n))->values(),
            'adjustments' => $target->adjustmentRequests()->get()
                ->map(fn (EmployeeTargetAdjustmentRequest $r) => $this->presentAdjustment($r))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBreakdown(EmployeeTargetBreakdown $breakdown): array
    {
        return [
            'id' => $breakdown->id,
            'employee_target_id' => $breakdown->employee_target_id,
            'title' => $breakdown->title,
            'description' => $breakdown->description,
            'target_value' => $breakdown->target_value === null ? null : (float) $breakdown->target_value,
            'target_unit' => $breakdown->target_unit,
            'progress' => (int) $breakdown->progress,
            'status' => $breakdown->status,
            'completed_at' => $breakdown->completed_at?->toIso8601String(),
            'sort_order' => (int) $breakdown->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMilestone(EmployeeTargetMilestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'employee_target_id' => $milestone->employee_target_id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'threshold_percent' => (int) $milestone->threshold_percent,
            'status' => $milestone->status,
            'achieved_at' => $milestone->achieved_at?->toIso8601String(),
            'sort_order' => (int) $milestone->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentNote(EmployeeTargetNote $note): array
    {
        return [
            'id' => $note->id,
            'employee_target_id' => $note->employee_target_id,
            'employee_id' => $note->employee_id,
            'author' => $note->relationLoaded('employee') && $note->employee
                ? $note->employee->full_name
                : null,
            'body' => $note->body,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAdjustment(EmployeeTargetAdjustmentRequest $request): array
    {
        return [
            'id' => $request->id,
            'employee_target_id' => $request->employee_target_id,
            'employee_id' => $request->employee_id,
            'target_title' => $request->target?->title,
            'employee_name' => $request->relationLoaded('employee') && $request->employee
                ? $request->employee->full_name
                : null,
            'employee_number' => $request->relationLoaded('employee') && $request->employee
                ? $request->employee->employee_number
                : null,
            'department_name' => $request->relationLoaded('employee') && $request->employee
                ? $request->employee->department?->name
                : null,
            'reason' => $request->reason,
            'request_type' => $request->request_type,
            'proposed' => [
                'target_value' => $request->proposed_target_value === null ? null : (float) $request->proposed_target_value,
                'target_unit' => $request->proposed_target_unit,
                'start_date' => $request->proposed_start_date?->toDateString(),
                'end_date' => $request->proposed_end_date?->toDateString(),
                'description' => $request->proposed_description,
            ],
            'status' => $request->status,
            'decision_note' => $request->decision_note,
            'reviewed_by' => $request->relationLoaded('reviewedBy') && $request->reviewedBy
                ? $request->reviewedBy->name
                : null,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'applied_at' => $request->applied_at?->toIso8601String(),
            'original_snapshot' => $request->original_snapshot,
            'requested_by' => $request->relationLoaded('requestedBy') && $request->requestedBy
                ? $request->requestedBy->name
                : null,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Guards
    // ─────────────────────────────────────────────────────────────────────────

    private function requireEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if ($employee === null) {
            abort(403, 'No employee profile linked to this account.');
        }

        return $employee;
    }

    private function findTargetFor(Employee $employee, int $targetId): EmployeeTarget
    {
        $target = EmployeeTarget::query()
            ->where('id', $targetId)
            ->where('employee_id', $employee->id)
            ->first();

        if ($target === null) {
            abort(404, 'Target not found.');
        }

        return $target;
    }

    private function findBreakdownFor(EmployeeTarget $target, int $breakdownId): EmployeeTargetBreakdown
    {
        $breakdown = $target->breakdowns()->whereKey($breakdownId)->first();

        if ($breakdown === null) {
            abort(404, 'Sub-target not found.');
        }

        return $breakdown;
    }

    private function findMilestoneFor(EmployeeTarget $target, int $milestoneId): EmployeeTargetMilestone
    {
        $milestone = $target->milestones()->whereKey($milestoneId)->first();

        if ($milestone === null) {
            abort(404, 'Milestone not found.');
        }

        return $milestone;
    }

    private function findNoteFor(EmployeeTarget $target, int $noteId): EmployeeTargetNote
    {
        $note = $target->notes()->whereKey($noteId)->first();

        if ($note === null) {
            abort(404, 'Note not found.');
        }

        return $note;
    }

    private function assertOwnsNote(Employee $employee, EmployeeTargetNote $note): void
    {
        if ((int) $note->employee_id !== (int) $employee->id) {
            abort(403, 'You can only manage your own notes.');
        }
    }

    private function findAdjustment(int $id): EmployeeTargetAdjustmentRequest
    {
        $request = EmployeeTargetAdjustmentRequest::with('target')->find($id);

        if ($request === null) {
            abort(404, 'Adjustment request not found.');
        }

        return $request;
    }

    private function assertPending(EmployeeTargetAdjustmentRequest $request): void
    {
        if ($request->status !== EmployeeTargetAdjustmentRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['This adjustment request has already been reviewed.'],
            ]);
        }
    }

    /**
     * The target's values before an approved change is applied.
     *
     * @return array<string, mixed>
     */
    private function snapshotTarget(EmployeeTarget $target): array
    {
        return [
            'title' => $target->title,
            'description' => $target->description,
            'target_value' => $target->target_value === null ? null : (float) $target->target_value,
            'target_unit' => $target->target_unit,
            'start_date' => $target->start_date?->toDateString(),
            'end_date' => $target->end_date?->toDateString(),
            'status' => $target->status,
        ];
    }

    /**
     * Only the proposed values that were actually supplied are applied, so an
     * approved request never blanks out target information it did not change.
     *
     * @return array<string, mixed>
     */
    private function approvedUpdates(EmployeeTargetAdjustmentRequest $request): array
    {
        $updates = [];

        if ($request->proposed_target_value !== null) {
            $updates['target_value'] = $request->proposed_target_value;
        }

        if ($request->proposed_target_unit !== null) {
            $updates['target_unit'] = $request->proposed_target_unit;
        }

        if ($request->proposed_start_date !== null) {
            $updates['start_date'] = $request->proposed_start_date->toDateString();
        }

        if ($request->proposed_end_date !== null) {
            $updates['end_date'] = $request->proposed_end_date->toDateString();
        }

        if ($request->proposed_description !== null) {
            $updates['description'] = $request->proposed_description;
        }

        return $updates;
    }

    /**
     * @param  Collection<int, EmployeeTarget>  $targets
     * @return array<string, mixed>
     */
    private function annualTarget(Collection $targets): array
    {
        $year = Carbon::now()->year;

        $scoped = $targets->filter(
            fn (EmployeeTarget $t) => $t->start_date === null || (int) $t->start_date->year === $year
        );

        $measured = $scoped
            ->map(fn (EmployeeTarget $t) => $this->progress($t)['progress'])
            ->filter(fn ($p) => $p !== null);

        $progress = $measured->isEmpty() ? null : round((float) $measured->avg(), 2);

        return [
            'year' => $year,
            'target' => (float) $scoped->sum(fn (EmployeeTarget $t) => (float) ($t->target_value ?? 0)),
            'completed' => $scoped->filter(fn (EmployeeTarget $t) => ($this->progress($t)['progress'] ?? -1) >= 100)->count(),
            'progress_percent' => $progress === null ? null : min(100, $progress),
            'label' => "Annual Target {$year}",
        ];
    }
}
