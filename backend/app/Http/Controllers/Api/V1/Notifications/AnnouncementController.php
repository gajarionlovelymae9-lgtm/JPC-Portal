<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Published announcements visible to the current user.
     */
    public function active(Request $request): JsonResponse
    {
        $announcements = Announcement::query()
            ->forUser($request->user())
            ->latest('published_at')
            ->limit(10)
            ->get();

        return ApiResponse::success('Active announcements retrieved', $announcements);
    }
}
