<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Notification delivery telemetry and admin retry capability.
 */
class NotificationDeliveryService
{
    public function metrics(): array
    {
        $stats = NotificationLog::query()
            ->selectRaw("
                COUNT(*) as total_sent,
                SUM(CASE WHEN channel = 'in_app' THEN 1 ELSE 0 END) as in_app_count,
                SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) as email_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'queued' OR status = 'processing' THEN 1 ELSE 0 END) as queued_count
            ")
            ->first();

        $totalSent = $stats->total_sent ?: 1;
        $readCount = DB::table('notifications')->whereNotNull('read_at')->count();
        $totalNotif = DB::table('notifications')->count() ?: 1;

        return [
            'total_sent' => (int) $stats->total_sent,
            'in_app_count' => (int) $stats->in_app_count,
            'email_count' => (int) $stats->email_count,
            'failed_count' => (int) $stats->failed_count,
            'queued_count' => (int) $stats->queued_count,
            'read_rate' => round(($readCount / $totalNotif) * 100, 1),
        ];
    }

    public function logs(array $filters = []): LengthAwarePaginator
    {
        $query = NotificationLog::query()
            ->with('user:id,name,email')
            ->latest('created_at');

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function retry(NotificationLog $log): NotificationLog
    {
        if ($log->status !== 'failed') {
            abort(422, 'Only failed notifications can be retried.');
        }

        $log->update([
            'status' => 'queued',
            'retry_count' => $log->retry_count + 1,
            'error_message' => null,
            'failed_at' => null,
        ]);

        return $log->fresh('user');
    }
}
