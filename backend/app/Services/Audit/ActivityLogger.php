<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Activity log writer using the (type, description, user, subject) ordering
 * expected by report / data-quality services. Delegates to ActivityLogService
 * so there is a single implementation of the append-only activity log.
 */
class ActivityLogger
{
    public function __construct(
        private readonly ActivityLogService $service
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $activityType,
        string $description,
        ?User $user = null,
        ?Model $subject = null,
        array $metadata = [],
    ): ActivityLog {
        return $this->service->log($user, $activityType, $subject, $description, $metadata);
    }
}