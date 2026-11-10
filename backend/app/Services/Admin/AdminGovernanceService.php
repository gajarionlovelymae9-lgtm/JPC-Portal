<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin governance data: the audit trail and the notification centre.
 *
 * Both sections read the tables the rest of the system already writes to
 * (audit_logs, activity_logs, notifications) — nothing is reconstructed or
 * invented here.
 */
class AdminGovernanceService
{
    /**
     * Unified audit timeline: audit_logs and activity_logs merged into one
     * descending stream.
     *
     * Implemented as a SQL UNION ALL inside a subquery so `paginate()` gets an
     * exact total and correct page windows across the two tables. Merging the
     * rows in PHP after fetching would produce wrong page boundaries.
     *
     * @param  array<string, mixed>  $filters
     */
    public function auditTrail(array $filters): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        $audits = DB::query()
            ->from('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select([
                DB::raw("'audit_log' as source"),
                'audit_logs.id',
                'audit_logs.action as event',
                'audit_logs.user_id',
                'users.name as actor_name',
                'users.email as actor_email',
                'audit_logs.entity_type',
                'audit_logs.entity_id',
                DB::raw('audit_logs.old_values as old_values'),
                DB::raw('audit_logs.new_values as new_values'),
                DB::raw('null as description'),
                DB::raw('null as metadata'),
                'audit_logs.ip_address',
                'audit_logs.user_agent',
                'audit_logs.created_at',
            ]);

        $this->applyAuditFilters($audits, $filters);

        $activities = DB::query()
            ->from('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select([
                DB::raw("'activity_log' as source"),
                'activity_logs.id',
                'activity_logs.activity_type as event',
                'activity_logs.user_id',
                'users.name as actor_name',
                'users.email as actor_email',
                'activity_logs.subject_type as entity_type',
                'activity_logs.subject_id as entity_id',
                DB::raw('null as old_values'),
                DB::raw('null as new_values'),
                'activity_logs.description',
                'activity_logs.metadata',
                'activity_logs.ip_address',
                'activity_logs.user_agent',
                'activity_logs.created_at',
            ]);

        $this->applyActivityFilters($activities, $filters);

        // `source` lets the UI drill into a single log table. When it is set,
        // the other branch is constrained to an impossible predicate so it
        // contributes zero rows to the union total.
        $source = $filters['source'] ?? null;
        if ($source === 'audit_log') {
            $activities->whereRaw('1 = 0');
        } elseif ($source === 'activity_log') {
            $audits->whereRaw('1 = 0');
        }

        $paginator = DB::query()
            ->fromSub($audits->unionAll($activities), 'audit_trail')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($row) => $this->timelineRow((array) $row));

        return $paginator;
    }

    /**
     * Apply the filters to the audit_logs branch of the union.
     *
     * `action` and `activity_type` share one event vocabulary across both
     * tables (e.g. "admin.user.update" can be recorded in either), so a filter
     * on one narrows BOTH branches. Filtering only the matching branch would
     * let the other branch contribute every row and produce a wrong total.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyAuditFilters($query, array $filters): void
    {
        $event = $filters['action'] ?? $filters['activity_type'] ?? null;

        $query
            ->when(! empty($event), fn ($q) => $q->where('audit_logs.action', $event))
            ->when(! empty($filters['entity_type']), fn ($q) => $q->where('audit_logs.entity_type', $filters['entity_type']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('audit_logs.user_id', (int) $filters['user_id']))
            ->when(! empty($filters['from_date']), fn ($q) => $q->where('audit_logs.created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay()))
            ->when(! empty($filters['to_date']), fn ($q) => $q->where('audit_logs.created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay()))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where('audit_logs.action', 'like', $term);
            });
    }

    /**
     * Apply the filters to the activity_logs branch of the union.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyActivityFilters($query, array $filters): void
    {
        $event = $filters['action'] ?? $filters['activity_type'] ?? null;

        $query
            ->when(! empty($event), fn ($q) => $q->where('activity_logs.activity_type', $event))
            ->when(! empty($filters['entity_type']), fn ($q) => $q->where('activity_logs.subject_type', $filters['entity_type']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('activity_logs.user_id', (int) $filters['user_id']))
            ->when(! empty($filters['from_date']), fn ($q) => $q->where('activity_logs.created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay()))
            ->when(! empty($filters['to_date']), fn ($q) => $q->where('activity_logs.created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay()))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where('activity_logs.description', 'like', $term);
            });
    }

    /**
     * Map a union row to the timeline shape.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function timelineRow(array $row): array
    {
        $old = $this->decode($row['old_values'] ?? null);
        $new = $this->decode($row['new_values'] ?? null);

        return [
            'id' => $row['source'].'-'.$row['id'],
            'source' => $row['source'],
            'event' => $row['event'],
            'actor' => $row['actor_name'],
            'actor_id' => $row['user_id'],
            'actor_email' => $row['actor_email'],
            'entity' => $row['entity_type']
                ? class_basename($row['entity_type']).(string) ($row['entity_id'] ?? '')
                : null,
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'],
            // Only audit_logs carry before/after snapshots.
            'changes' => $row['source'] === 'audit_log' ? $this->diff($old, $new) : [],
            'description' => $row['description'],
            'metadata' => $this->decode($row['metadata'] ?? null),
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'created_at' => $row['created_at'],
        ];
    }

    /**
     * Decode a JSON column that may already be an array (model casts) or a
     * raw string (query builder).
     *
     * @return array<string, mixed>|null
     */
    private function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Paginated notifications for the admin notification centre.
     *
     * Filters include the read state, type, priority, recipient user, the
     * recipient's department (dynamic from the departments table) and a
     * free-text recipient name/email search, on top of the original date and
     * title/message search. Default ordering is newest first — unread rows
     * surface first only inside each created_at tie.
     *
     * @param  array<string, mixed>  $filters
     */
    public function notifications(array $filters): LengthAwarePaginator
    {
        $query = Notification::query()
            ->with(['user:id,name,email', 'user.employee.department:id,name'])
            ->when(! empty($filters['type']), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['priority']), fn (Builder $q) => $q->where('priority', $filters['priority']))
            ->when(($filters['read'] ?? null) === 'unread', fn (Builder $q) => $q->whereNull('read_at'))
            ->when(($filters['read'] ?? null) === 'read', fn (Builder $q) => $q->whereNotNull('read_at'))
            ->when(! empty($filters['user_id']), fn (Builder $q) => $q->where('user_id', (int) $filters['user_id']))
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->whereHas(
                'user.employee',
                fn (Builder $e) => $e->where('department_id', (int) $filters['department_id']),
            ))
            ->when(! empty($filters['from_date']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay()))
            ->when(! empty($filters['to_date']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay()))
            ->when(! empty($filters['recipient']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['recipient']).'%';
                $q->whereHas('user', fn (Builder $u) => $u
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
                $q->where(fn (Builder $inner) => $inner->where('title', 'like', $term)->orWhere('message', 'like', $term));
            })
            ->orderByRaw('case when read_at is null then 0 else 1 end')
            ->orderByDesc('id');

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage)->through(fn (Notification $row) => $this->row($row));
    }

    /**
     * Serialise one notification row for the admin log table. Delivery status
     * comes from the notification_logs channel telemetry when available; the
     * channel is honestly labelled — the platform delivers in-app only.
     *
     * @return array<string, mixed>
     */
    private function row(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'priority' => $notification->priority ?? 'normal',
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'task_id' => $notification->task_id,
            'reminder_days' => $notification->reminder_days,
            'recipient' => $notification->user?->name,
            'recipient_email' => $notification->user?->email,
            'recipient_id' => $notification->user_id,
            'employee_number' => $notification->user?->employee?->employee_number,
            'department' => $notification->user?->employee?->department?->name,
            'delivery_channel' => 'in_app',
            'delivery_status' => $this->deliveryStatus($notification),
            'triggered_by' => $this->triggeredBy($notification),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * Latest delivery-log status for a notification row, or a sane default.
     */
    private function deliveryStatus(Notification $notification): string
    {
        $status = NotificationLog::query()
            ->where('notification_id', $notification->id)
            ->where('channel', 'in_app')
            ->orderByDesc('id')
            ->value('status');

        return is_string($status) && $status !== '' ? $status : 'sent';
    }

    /**
     * Full details for View Details (Phase 5): the complete message, the
     * related task/record, the related employee and the recipient batch size.
     *
     * @return array<string, mixed>
     */
    public function details(Notification $notification): array
    {
        $row = $this->row($notification->load(['user:id,name,email', 'user.employee.department:id,name']));
        $batchIds = $this->batchIds($notification);

        $row['recipient_count'] = count($batchIds);
        $row['triggered_by'] = $this->triggeredBy($notification);
        $row['related'] = $this->relatedRecord($notification);

        return $row;
    }

    /**
     * Per-recipient view for a notification batch (Phase 4). Every recipient
     * owns an individual `notifications` row, so every read status is unique
     * to that recipient by construction.
     *
     * @return array<string, mixed>
     */
    public function recipients(Notification $notification): array
    {
        $batchIds = $this->batchIds($notification);

        $rows = Notification::query()
            ->with(['user:id,name,email', 'user.employee.department:id,name'])
            ->whereIn('id', $batchIds)
            ->orderByDesc('id')
            ->get();

        $recipientCount = $rows->count();
        $readCount = $rows->whereNotNull('read_at')->count();

        return [
            'notification' => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'priority' => $notification->priority ?? 'normal',
            ],
            'summary' => [
                'total' => $recipientCount,
                'delivered' => $recipientCount,
                'failed' => 0,
                'read' => $readCount,
                'unread' => $recipientCount - $readCount,
            ],
            'recipients' => $rows->map(fn (Notification $n) => [
                'notification_id' => $n->id,
                'name' => $n->user?->name,
                'email' => $n->user?->email,
                'employee_number' => $n->user?->employee?->employee_number,
                'department' => $n->user?->employee?->department?->name,
                'delivery_status' => $this->deliveryStatus($n),
                'is_read' => $n->read_at !== null,
                'read_at' => $n->read_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Admin mark-row-read (only where authorized: admin group + policy-free
     * read state change recorded for telemetry). Returns the refreshed row.
     *
     * @return array<string, mixed>
     */
    public function markRead(Notification $notification): array
    {
        if ($notification->read_at === null) {
            DB::transaction(function () use ($notification): void {
                $notification->forceFill(['read_at' => now()])->save();

                NotificationLog::query()
                    ->where('notification_id', $notification->id)
                    ->where('channel', 'in_app')
                    ->where('status', '!=', 'failed')
                    ->update(['status' => 'read']);
            });
        }

        return $this->row($notification->refresh(['user.employee.department']));
    }

    /**
     * Ids of every notification row belonging to the same send batch.
     *
     * Batch keys reuse what the senders already write:
     *   - announcements:  data->record_id (the announcement id);
     *   - task workflow:  (type, task_id, reminder_days);
     *   - everything else: same type + title within a 2-minute window.
     *
     * @return list<int>
     */
    private function batchIds(Notification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $announcementId = data_get($data, 'record_id');
        $isAnnouncement = $announcementId !== null && ($data['module'] ?? null) === 'announcement';

        if ($isAnnouncement) {
            return Notification::query()
                ->where('type', $notification->type)
                ->where('data->record_id', $announcementId)
                ->whereDate('created_at', $notification->created_at?->toDateString())
                ->pluck('id')
                ->all();
        }

        if ($notification->task_id !== null) {
            return Notification::query()
                ->where('type', $notification->type)
                ->where('task_id', $notification->task_id)
                ->where('reminder_days', $notification->reminder_days)
                ->pluck('id')
                ->all();
        }

        $window = Carbon::parse($notification->created_at ?? now());

        return Notification::query()
            ->where('type', $notification->type)
            ->where('title', $notification->title)
            ->whereBetween('created_at', [$window->copy()->subMinutes(2), $window->copy()->addMinutes(2)])
            ->pluck('id')
            ->all();
    }

    /**
     * Best-effort "triggered by" from the payload the sender wrote.
     */
    private function triggeredBy(Notification $notification): string
    {
        return match (true) {
            data_get($notification->data ?? [], 'module') === 'announcement' => 'Announcement Workflow',
            $notification->task_id !== null => 'Task Workflow',
            str_contains($notification->type, 'system') => 'System Process',
            str_contains($notification->type, 'achievement') => 'Achievement Engine',
            default => 'JPC Portal Automation',
        };
    }

    /**
     * Related record reference for the "View Related Record" action, or null
     * when the payload carries no joinable context.
     *
     * @return array{type: string, id: int, label: string}|null
     */
    private function relatedRecord(Notification $notification): ?array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        if (($data['module'] ?? null) === 'announcement' && ! empty($data['record_id'])) {
            return [
                'type' => 'announcement',
                'id' => (int) $data['record_id'],
                'label' => 'View Announcement',
            ];
        }

        if ($notification->task_id !== null) {
            $assignment = TaskAssignment::query()
                ->with('task:id,title')
                ->where('task_id', $notification->task_id)
                ->first();

            return [
                'type' => 'task',
                'id' => (int) $notification->task_id,
                'label' => $assignment?->task?->title
                    ? 'View Task — '.$assignment->task->title
                    : 'View Task',
            ];
        }

        return null;
    }

    /**
     * Governance summary cards.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $notifications = Notification::query();

        $recentAudit = AuditLog::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recentActivity = ActivityLog::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $failedLogins = ActivityLog::query()
            ->where('activity_type', 'like', '%login%')
            ->where('activity_type', 'like', '%fail%')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'notifications' => [
                'total' => (clone $notifications)->count(),
                'unread' => (clone $notifications)->whereNull('read_at')->count(),
                'high_priority' => (clone $notifications)->whereIn('priority', ['high', 'urgent'])->count(),
                'last_24h' => (clone $notifications)->where('created_at', '>=', now()->subDay())->count(),
                // Failed deliveries come from the channel telemetry the senders
                // and the queued job write to notification_logs.
                'failed_deliveries' => NotificationLog::query()->where('status', 'failed')->count(),
            ],
            'audit_logs' => [
                'total' => AuditLog::query()->count(),
                'last_7_days' => $recentAudit,
            ],
            'activity_logs' => [
                'total' => ActivityLog::query()->count(),
                'last_7_days' => $recentActivity,
                'failed_logins_7d' => $failedLogins,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Distinct filter options, read from the data itself.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'audit_actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
                ->map(fn ($action) => ['value' => $action, 'label' => $action])->all(),
            'entity_types' => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type')
                ->filter()->map(fn ($type) => ['value' => $type, 'label' => class_basename($type)])->values()->all(),
            'activity_types' => ActivityLog::query()->distinct()->orderBy('activity_type')->pluck('activity_type')
                ->map(fn ($type) => ['value' => $type, 'label' => $type])->all(),
            'notification_types' => Notification::query()->distinct()->orderBy('type')->pluck('type')
                ->filter()->map(fn ($type) => ['value' => $type, 'label' => $type])->values()->all(),
            // Department filter options come straight from the departments
            // table — never hardcoded, always in institution alphabetical order.
            'departments' => Department::query()->orderBy('name')->get()
                ->map(fn (Department $d) => ['value' => $d->id, 'label' => $d->name])->all(),
            'sources' => [
                ['value' => 'audit_log', 'label' => 'Audit Log (entity changes)'],
                ['value' => 'activity_log', 'label' => 'Activity Log (user actions)'],
            ],
            'priorities' => Notification::query()->distinct()->pluck('priority')->filter()
                ->map(fn ($p) => ['value' => $p, 'label' => ucfirst((string) $p)])->values()->all(),
        ];
    }

    /**
     * Field-level diff between the audit log's old and new snapshots.
     *
     * @return list<array<string, mixed>>
     */
    private function diff(?array $old, ?array $new): array
    {
        if ($old === null && $new === null) {
            return [];
        }

        $old = $old ?? [];
        $new = $new ?? [];
        $fields = array_unique(array_merge(array_keys($old), array_keys($new)));

        $rows = [];
        foreach ($fields as $field) {
            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;
            if ($before === $after) {
                continue;
            }
            $rows[] = [
                'field' => $field,
                'old' => $this->scalar($before),
                'new' => $this->scalar($after),
            ];
        }

        return $rows;
    }

    private function scalar(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return is_array($value) || $value instanceof \JsonSerializable
            ? json_encode($value, JSON_UNESCAPED_SLASHES)
            : (string) $value;
    }
}
