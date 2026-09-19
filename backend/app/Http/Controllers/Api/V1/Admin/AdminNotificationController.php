<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDeliveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationDeliveryService $delivery,
    ) {}

    public function metrics(): JsonResponse
    {
        return ApiResponse::success(
            'Notification metrics retrieved',
            $this->delivery->metrics(),
        );
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = $this->delivery->logs($request->only([
            'channel', 'status', 'type', 'from', 'to', 'per_page',
        ]));

        return ApiResponse::success('Notification logs retrieved', $logs);
    }

    public function retry(NotificationLog $notificationLog): JsonResponse
    {
        $log = $this->delivery->retry($notificationLog);

        return ApiResponse::success('Notification retry queued', $log);
    }
}
