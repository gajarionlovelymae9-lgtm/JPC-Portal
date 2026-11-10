<?php

namespace App\Services\Hr;

use App\Models\Announcement;
use App\Services\Admin\AdminMonitoringService;

/**
 * HR institutional calendar.
 *
 * Reuses the shared institution calendar builder (task deadlines, report
 * deadlines, KPI period ends, achievements and published announcements) and
 * enriches every announcement entry with its full record so the HR UI can
 * open a detail view without a second request.
 */
class HrCalendarService
{
    public function __construct(
        private readonly AdminMonitoringService $monitoring,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function calendar(array $filters): array
    {
        $events = $this->monitoring->calendar($filters);

        // Attach the full announcement record to each calendar entry drawn
        // from the announcements table; the detail panel renders from this.
        foreach ($events as $index => $event) {
            if (! is_string($event['id'] ?? null) || ! str_starts_with($event['id'], 'announcement-')) {
                continue;
            }

            $announcement = Announcement::query()
                ->where('status', 'published')
                ->with('creator:id,name')
                ->find((int) substr($event['id'], strlen('announcement-')));

            if (! $announcement) {
                // The calendar window may include rows that were archived or
                // taken down after the entry was built — drop it instead of
                // showing an event without a source record.
                unset($events[$index]);

                continue;
            }

            $connector = $event;
            $connector['announcement'] = $this->detail($announcement);
            $events[$index] = $connector;
        }

        $events = array_values($events);

        return [
            'events' => $events,
            'sources' => $this->monitoring->calendarSources(),
            'range' => [
                'from' => $filters['from'] ?? now()->startOfMonth()->toDateString(),
                'to' => $filters['to'] ?? now()->endOfMonth()->toDateString(),
            ],
        ];
    }

    /**
     * Full payload for the detail panel: title, message, date/time window,
     * category, audience and publisher.
     *
     * @return array<string, mixed>
     */
    private function detail(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'category' => $announcement->category,
            'priority' => $announcement->priority,
            'audience_type' => $announcement->audience_type,
            'audience_id' => $announcement->audience_id,
            'start_date' => $announcement->start_date?->toIso8601String(),
            'end_date' => $announcement->end_date?->toIso8601String(),
            'published_at' => $announcement->published_at?->toIso8601String(),
            'created_by' => $announcement->creator?->name,
        ];
    }
}
