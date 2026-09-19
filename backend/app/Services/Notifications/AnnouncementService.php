<?php

namespace App\Services\Notifications;

use App\Enums\NotificationEvent;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

    public function delete(Announcement $announcement): void
    {
        if (! in_array($announcement->status, ['draft', 'expired'])) {
            abort(422, 'Only draft or expired announcements can be deleted.');
        }

        $announcement->delete();
    }

    /**
     * Create in-app notification for each targeted user.
     */
    private function notifyAudience(Announcement $announcement): void
    {
        $users = $this->resolveAudience($announcement);

        $now = now();
        $records = [];

        foreach ($users as $user) {
            $records[] = [
                'user_id' => $user->id,
                'type' => NotificationEvent::AnnouncementPublished->value,
                'title' => '📢 ' . $announcement->title,
                'message' => \Illuminate\Support\Str::limit($announcement->message, 200),
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
    }

    private function resolveAudience(Announcement $announcement): \Illuminate\Support\Collection
    {
        return match ($announcement->audience_type) {
            'all' => User::query()->where('is_active', true)->get(['id']),
            'role' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', $announcement->audience_id))
                ->get(['id']),
            'department' => User::query()
                ->where('is_active', true)
                ->whereHas('employee', fn ($q) => $q->where('department_id', $announcement->audience_id))
                ->get(['id']),
            'user' => User::query()
                ->where('id', $announcement->audience_id)
                ->where('is_active', true)
                ->get(['id']),
            default => collect(),
        };
    }
}
