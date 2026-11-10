<?php

namespace App\Services\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only activity log writer.
 *
 * Captures the acting user, a human-readable description, the polymorphic
 * subject, the request context (IP address and user agent when available) and
 * any structured metadata. Records are write-only (ActivityLog is $guarded);
 * no service exposes an update path, keeping the timeline immutable.
 */
class ActivityLogService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        ?User $user,
        string $activityType,
        ?Model $subject = null,
        ?string $description = null,
        array $metadata = [],
    ): ActivityLog {
        $log = new ActivityLog;

        $log->forceFill([
            'user_id' => $user?->id,
            'activity_type' => $activityType,
            'description' => $description ?? $activityType,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $this->requestIp(),
            'user_agent' => $this->requestUserAgent(),
        ]);

        $log->save();

        return $log;
    }

    public function login(User $user): void
    {
        $this->log($user, 'auth.login', description: 'Signed in to the portal.', metadata: ['method' => 'password']);
    }

    public function logout(User $user): void
    {
        $this->log($user, 'auth.logout', description: 'Signed out of the portal.');
    }

    private function requestIp(): ?string
    {
        $ip = request()->ip();

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    private function requestUserAgent(): ?string
    {
        $agent = request()->userAgent();

        return is_string($agent) && $agent !== '' ? $agent : null;
    }
}
