<?php

namespace App\Services\Notifications;

use App\Enums\NotificationEvent;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnnouncementService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Announcement::query()->with('creator')->latest('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['audience_type'])) {
            $query->where('audience_type', $filters['audience_type']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(User $admin, array $data): Announcement
    {
        return Announcement::create([
            'title' => $data['title'],
            'message' => $data['message'],
            'priority' => $data['priority'] ?? 'normal',
            'category' => $data['category'] ?? 'general',
            'audience_type' => $data['audience_type'] ?? 'all',
            'audience_id' => $data['audience_id'] ?? null,
            'status' => 'draft',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_by' => $admin->id,
        ]);
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        if (! in_array($announcement->status, ['draft', 'scheduled'])) {
            abort(422, 'Only draft or scheduled announcements can be edited.');
        }

        $announcement->update(array_filter([
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'priority' => $data['priority'] ?? null,
            'category' => $data['category'] ?? null,
            'audience_type' => $data['audience_type'] ?? null,
            'audience_id' => $data['audience_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ], fn ($v) => $v !== null));

        return $announcement->fresh('creator');
    }

    public function publish(Announcement $announcement): Announcement
    {
        if ($announcement->status === 'published') {
            abort(422, 'Announcement is already published.');
        }

        DB::transaction(function () use ($announcement) {
            $announcement->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            $this->notifyAudience($announcement);
        });

        return $announcement->fresh('creator');
    }

    /**
     * Queue a draft/scheduled announcement for automatic publication at the
     * given future time. It stays invisible until the scheduled command
     * promotes it to "published".
     */
    public function schedule(Announcement $announcement, string $publishAt): Announcement
    {
        if (! in_array($announcement->status, ['draft', 'scheduled'])) {
            abort(422, 'Only draft or scheduled announcements can be scheduled.');
        }

        $publishTime = Carbon::parse($publishAt);

        if ($publishTime->lessThanOrEqualTo(now())) {
            abort(422, 'The scheduled publication time must be in the future.');
        }

        $announcement->update([
            'status' => 'scheduled',
            'published_at' => $publishTime,
        ]);

        return $announcement->fresh('creator');
    }

    /**
     * Cancel a schedule and return the announcement to draft.
     */
    public function unschedule(Announcement $announcement): Announcement
    {
        if ($announcement->status !== 'scheduled') {
            abort(422, 'Only scheduled announcements can be unscheduled.');
        }

        $announcement->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        return $announcement->fresh('creator');
    }

    /**
     * Archive a live or scheduled announcement. The row and its history are
     * preserved; only its visibility changes.
     */
    public function archive(Announcement $announcement): Announcement
    {
        if (! in_array($announcement->status, ['published', 'scheduled'])) {
            abort(422, 'Only published or scheduled announcements can be archived.');
        }

        $announcement->update(['status' => 'expired']);

        return $announcement->fresh('creator');
    }

    /**
     * Promote every scheduled announcement whose publication time has passed.
     * Returns how many were published.
     */
    public function publishDue(): int
    {
        $due = Announcement::query()
            ->where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($due as $announcement) {
            DB::transaction(function () use ($announcement) {
                $announcement->update(['status' => 'published']);

                $this->notifyAudience($announcement);
            });

            $count++;
        }

        return $count;
    }

    public function delete(Announcement $announcement): void
    {
        if (! in_array($announcement->status, ['draft', 'expired'])) {
            abort(422, 'Only draft or expired announcements can be deleted.');
        }

        $announcement->delete();
    }

    /**
     * Create in-app notification for each targeted user.
     *
     * Recipients whose in-app preference is off are excluded up front (the
     * event is not on the critical list), users already notified for this
     * announcement are skipped (duplicate prevention on data->record_id), and
     * every created row also gets a matching in-app delivery log so the
     * Delivery Monitor reflects the actual fanout.
     */
    private function notifyAudience(Announcement $announcement): void
    {
        $users = $this->resolveAudience($announcement);

        // Preference routing: resolve rows of users who explicitly disabled
        // in-app for announcements. Missing rows default to enabled.
        $optOutUserIds = NotificationPreference::query()
            ->where('event', NotificationEvent::AnnouncementPublished->value)
            ->where('in_app_enabled', false)
            ->pluck('user_id')
            ->all();

        if ($optOutUserIds !== []) {
            $users = $users->reject(fn ($u) => in_array((int) $u->id, $optOutUserIds, true));
            $users = $users->values();
        }

        // Duplicate prevention: everyone already notified of this exact
        // announcement (e.g. publish re-run after a mid-fanout failure).
        $alreadyNotified = Notification::query()
            ->where('type', NotificationEvent::AnnouncementPublished->value)
            ->where('data->record_id', $announcement->id)
            ->pluck('user_id')
            ->all();

        $users = $users->reject(fn ($u) => in_array((int) $u->id, $alreadyNotified, true))->values();

        if ($users->isEmpty()) {
            return;
        }

        $now = now();
        $records = [];

        foreach ($users as $user) {
            $records[] = [
                'user_id' => $user->id,
                'type' => NotificationEvent::AnnouncementPublished->value,
                'title' => '📢 '.$announcement->title,
                'message' => Str::limit($announcement->message, 200),
                'priority' => $announcement->priority,
                'data' => json_encode([
                    'module' => 'announcement',
                    'record_id' => $announcement->id,
                    'action' => 'view_announcement',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert in chunks of 100
        foreach (array_chunk($records, 100) as $chunk) {
            Notification::insert($chunk);
        }

        // Delivery telemetry for the fanout — one in-app log per created row.
        $created = Notification::query()
            ->where('type', NotificationEvent::AnnouncementPublished->value)
            ->where('data->record_id', $announcement->id)
            ->whereBetween('created_at', [$now->copy()->subSeconds(5), $now->copy()->addSeconds(5)])
            ->get(['id', 'user_id', 'type']);

        $logs = $created->map(fn ($n) => [
            'notification_id' => $n->id,
            'user_id' => $n->user_id,
            'type' => $n->type,
            'channel' => 'in_app',
            'status' => 'sent',
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($logs, 100) as $chunk) {
            NotificationLog::insert($chunk);
        }
    }

    private function resolveAudience(Announcement $announcement): Collection
    {
        return match ($announcement->audience_type) {
            'all' => User::query()->where('status', 'active')->get(['id']),
            'role' => User::query()
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->where('name', $announcement->audience_id))
                ->get(['id']),
            'department' => User::query()
                ->where('status', 'active')
                ->whereHas('employee', fn ($q) => $q->where('department_id', $announcement->audience_id))
                ->get(['id']),
            'user' => User::query()
                ->where('id', $announcement->audience_id)
                ->where('status', 'active')
                ->get(['id']),
            default => collect(),
        };
    }
}
