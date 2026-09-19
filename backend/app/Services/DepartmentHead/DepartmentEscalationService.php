<?php

namespace App\Services\DepartmentHead;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentEscalation;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;

class DepartmentEscalationService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context
    ) {}

    public function listEscalations(User $user, array $filters = []): array
    {
        $department = $this->context->resolveDepartment($user, $filters['department_id'] ?? null);

        $query = DepartmentEscalation::where('department_id', $department->id)
            ->with(['creator:id,name', 'resolver:id,name']);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $escalations = $query->orderBy('created_at', 'desc')->get();

        return $escalations->map(function ($e) {
            return [
                'id' => $e->id,
                'title' => $e->title,
                'type' => $e->type,
                'reason' => $e->reason,
                'priority' => $e->priority,
                'status' => $e->status,
                'resolution_notes' => $e->resolution_notes,
                'created_by_name' => $e->creator?->name ?? 'Department Head',
                'resolver_name' => $e->resolver?->name,
                'created_at' => $e->created_at?->toIso8601String(),
                'resolved_at' => $e->resolved_at?->toIso8601String(),
            ];
        })->toArray();
    }

    public function createEscalation(User $user, array $data): array
    {
        $department = $this->context->resolveDepartment($user, $data['department_id'] ?? null);

        $escalation = DepartmentEscalation::create([
            'department_id' => $department->id,
            'created_by' => $user->id,
            'title' => $data['title'],
            'type' => $data['type'] ?? 'general',
            'reason' => $data['reason'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'department_head.escalation.created',
            'auditable_type' => DepartmentEscalation::class,
            'auditable_id' => $escalation->id,
            'new_values' => [
                'title' => $escalation->title,
                'type' => $escalation->type,
                'priority' => $escalation->priority,
            ],
        ]);

        // Notify Admins
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminUsers = $adminRole->users()->get();
            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'escalation',
                    'title' => "Department Escalation: {$escalation->title}",
                    'message' => "Escalation raised by {$department->name} ({$user->name}): {$escalation->reason}",
                    'is_read' => false,
                ]);
            }
        }

        return [
            'id' => $escalation->id,
            'title' => $escalation->title,
            'status' => $escalation->status,
        ];
    }
}
