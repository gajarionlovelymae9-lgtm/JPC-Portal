<?php

namespace App\Services\DataQuality;

use App\Enums\DataQualityIssueSeverity;
use App\Enums\DataQualityIssueStatus;
use App\Models\DataQualityIssue;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DataQualityIssueService
{
    public function getFilteredIssues(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DataQualityIssue::query()->with(['department', 'employee', 'reviewer', 'resolver']);

        $this->applyRoleScope($query, $user);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['issue_type'])) {
            $query->where('issue_type', $filters['issue_type']);
        }
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function applyRoleScope(Builder $query, User $user): Builder
    {
        // Admin & HR: institution-wide access
        if ($user->hasRole('admin') || $user->hasRole('hr')) {
            return $query;
        }

        // Department Head: scoped to their department
        if ($user->hasRole('department_head')) {
            $deptId = $user->employee?->department_id;
            return $query->where(function (Builder $q) use ($deptId) {
                $q->where('department_id', $deptId)
                  ->orWhereNull('department_id');
            });
        }

        // Employee: own records only
        if ($user->employee) {
            return $query->where('employee_id', $user->employee->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
