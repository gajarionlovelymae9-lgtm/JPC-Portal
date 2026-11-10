<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Notifications\AnnouncementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $list = $this->announcements->list($request->only([
            'status', 'audience_type', 'category', 'search', 'per_page',
        ]));

        return ApiResponse::success('Announcements retrieved', $list);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,normal,high,critical',
            'category' => 'nullable|string|in:'.implode(',', Announcement::CATEGORIES),
            'audience_type' => 'nullable|in:all,department,role,user',
            'audience_id' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $announcement = $this->announcements->create(
            $request->user(),
            $request->only(['title', 'message', 'priority', 'category', 'audience_type', 'audience_id', 'start_date', 'end_date']),
        );

        return ApiResponse::success('Announcement created', $announcement->load('creator'), 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
            'priority' => 'nullable|in:low,normal,high,critical',
            'category' => 'nullable|string|in:'.implode(',', Announcement::CATEGORIES),
            'audience_type' => 'nullable|in:all,department,role,user',
            'audience_id' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $updated = $this->announcements->update(
            $announcement,
            $request->only(['title', 'message', 'priority', 'category', 'audience_type', 'audience_id', 'start_date', 'end_date']),
        );

        return ApiResponse::success('Announcement updated', $updated);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->announcements->delete($announcement);

        return ApiResponse::success('Announcement deleted');
    }

    public function publish(Announcement $announcement): JsonResponse
    {
        $published = $this->announcements->publish($announcement);

        return ApiResponse::success('Announcement published', $published);
    }

    public function schedule(Request $request, Announcement $announcement): JsonResponse
    {
        $request->validate([
            'published_at' => 'required|date|after:now',
        ]);

        $scheduled = $this->announcements->schedule($announcement, $request->input('published_at'));

        return ApiResponse::success('Announcement scheduled', $scheduled);
    }

    public function unschedule(Announcement $announcement): JsonResponse
    {
        $unscheduled = $this->announcements->unschedule($announcement);

        return ApiResponse::success('Announcement returned to draft', $unscheduled);
    }

    public function archive(Announcement $announcement): JsonResponse
    {
        $archived = $this->announcements->archive($announcement);

        return ApiResponse::success('Announcement archived', $archived);
    }
}
