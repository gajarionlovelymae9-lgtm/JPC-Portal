<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Development-only samples for the delivery-monitoring tables that remain
 * empty after the core demo flows (which push in-app rows but do not emit
 * NotificationLog/audit entries): notification_logs and audit_logs.
 *
 * DEVELOPMENT SAMPLE DATA only. Gated by config("jpc.seed_development.enabled")
 * and skipped once either table already has rows.
 */
class DevelopmentDeliveryMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('jpc.seed_development.enabled')) {
            $this->command?->warn('DevelopmentDeliveryMonitoringSeeder skipped: development seeding is disabled.');

            return;
        }

        if (NotificationLog::query()->count() > 0 || AuditLog::query()->count() > 0) {
            $this->command?->warn('DevelopmentDeliveryMonitoringSeeder skipped: delivery-monitoring samples already exist.');

            return;
        }

        DB::transaction(function () {
            $this->seedNotificationLogs();
            $this->seedAuditLogs();
        });
    }

    private function seedNotificationLogs(): void
    {
        $users = User::query()->whereIn('email', [
            'admin@jpc.local',
            'hr@jpc.local',
            'departmenthead@jpc.local',
            'employee@jpc.local',
        ])->pluck('id', 'email');

        $notification = Notification::query()->first();

        $samples = [
            ['user' => 'admin@jpc.local', 'type' => 'report_submitted', 'channel' => 'in_app', 'status' => 'sent'],
            ['user' => 'hr@jpc.local', 'type' => 'system', 'channel' => 'in_app', 'status' => 'sent'],
            ['user' => 'departmenthead@jpc.local', 'type' => 'report_submitted', 'channel' => 'in_app', 'status' => 'sent'],
            ['user' => 'employee@jpc.local', 'type' => 'report_approved', 'channel' => 'in_app', 'status' => 'sent'],
            ['user' => 'employee@jpc.local', 'type' => 'report_approved', 'channel' => 'email', 'status' => 'queued'],
            ['user' => 'departmenthead@jpc.local', 'type' => 'deadline_reminder', 'channel' => 'in_app', 'status' => 'sent'],
        ];

        foreach ($samples as $i => $sample) {
            $userId = $users[$sample['user']] ?? null;
            if ($userId === null) {
                continue;
            }

            NotificationLog::create([
                'notification_id' => $notification?->id,
                'user_id' => $userId,
                'type' => $sample['type'],
                'channel' => $sample['channel'],
                'status' => $sample['status'],
                'retry_count' => 0,
                'sent_at' => now()->subDays(count($samples) - $i)->subMinutes(10),
            ]);
        }
    }

    private function seedAuditLogs(): void
    {
        $users = User::query()->whereIn('email', [
            'admin@jpc.local',
            'hr@jpc.local',
            'departmenthead@jpc.local',
        ])->pluck('id', 'email');

        $actions = [
            ['user' => 'admin@jpc.local', 'action' => 'admin.report.override', 'entity_type' => 'App\Models\Report', 'entity_id' => 1],
            ['user' => 'hr@jpc.local', 'action' => 'hr.profile.export', 'entity_type' => 'App\Models\Employee', 'entity_id' => 1],
            ['user' => 'departmenthead@jpc.local', 'action' => 'report.review', 'entity_type' => 'App\Models\Report', 'entity_id' => 1],
            ['user' => 'admin@jpc.local', 'action' => 'admin.user.update', 'entity_type' => 'App\Models\User', 'entity_id' => 4],
            ['user' => 'hr@jpc.local', 'action' => 'hr.employee.update', 'entity_type' => 'App\Models\Employee', 'entity_id' => 4],
        ];

        foreach ($actions as $i => $action) {
            $userId = $users[$action['user']] ?? null;
            if ($userId === null) {
                continue;
            }

            AuditLog::create([
                'user_id' => $userId,
                'action' => $action['action'],
                'entity_type' => $action['entity_type'],
                'entity_id' => $action['entity_id'],
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'approved'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Development Seeder',
            ]);
        }
    }
}