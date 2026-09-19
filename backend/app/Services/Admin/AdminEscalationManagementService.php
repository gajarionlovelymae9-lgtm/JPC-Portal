<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\DepartmentEscalation;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class AdminEscalationManagementService
{
    public function listEscalations(array $filters = []): array
    {
        $query = DepartmentEscalation::with([
            'department:id,name',
            'creator:id,name',
            'resolver:id,name',
        ]);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $escalations = $query->orderBy('created_at', 'desc')->get();

        return $escalations->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'department_name' => $e->department?->name ?? 'Academic Department',
            'type' => $e->type,
            'reason' => $e->reason,
            'priority' => $e->priority,
            'status' => $e->status,
            'resolution_notes' => $e->resolution_notes,
            'created_by_name' => $e->creator?->name ?? 'Department Head',
            'resolver_name' => $e->resolver?->name,
            'created_at' => $e->created_at?->toIso8601String(),
            'resolved_at' => $e->resolved_at?->toIso8601String(),
        ])->toArray();
    }

    public function resolveEscalation(User $user, DepartmentEscalation $escalation, array $data): array
    {
        $status = $data['status'] ?? 'resolved'; // resolved, under_review, closed
        $notes = $data['resolution_notes'];

        $escalation->update([
            'status' => $status,
            'resolution_notes' => $notes,
            'resolved_by' => $user->id,
            'resolved_at' => Carbon::now(),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => "admin.escalation.{$status}",
            'auditable_type' => DepartmentEscalation::class,
            'auditable_id' => $escalation->id,
            'new_values' => [
                'status' => $status,
                'resolution_notes' => $notes,
            ],
        ]);

        // Notify Department Head
        if ($escalation->created_by) {
            Notification::create([
                'user_id' => $escalation->created_by,
                'type' => 'escalation_resolved',
                'title' => "Escalation Update: {$escalation->title}",
                'message' => "Administration has updated this issue to {$status}: {$notes}",
                'is_read' => false,
            ]);
        }

        return [
            'id' => $escalation->id,
            'status' => $status,
            'resolution_notes' => $notes,
        ];
    }
}