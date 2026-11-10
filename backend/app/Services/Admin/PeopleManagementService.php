<?php

namespace App\Services\Admin;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Composes the People Management header summary from the other services.
 *
 * Nothing is recomputed where an existing service already owns the query:
 * account totals reuse `AdminUserManagementService::summary()` and department
 * totals reuse `AdminDepartmentManagementService::summary()`. Only the
 * employee block is owned here because no existing service aggregates
 * employment-status counts for a department-agnostic card row.
 */
class PeopleManagementService
{
    public function __construct(
        private readonly AdminUserManagementService $userService,
        private readonly AdminDepartmentManagementService $departmentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $userSummary = $this->userService->summary();
        $departmentSummary = $this->departmentService->summary();

        return [
            'employees' => [
                'total' => Employee::count(),
                'active' => Employee::query()->where('employment_status', 'active')->count(),
                'inactive' => Employee::query()->where('employment_status', '!=', 'active')->count(),
                'with_late_reports' => Employee::query()
                    ->whereHas('reports', fn ($q) => $q->where('is_late', true))
                    ->count(),
            ],
            'departments' => $departmentSummary,
            'accounts' => [
                'total' => (int) $userSummary['total_users'],
                'active' => (int) $userSummary['active_users'],
                'inactive' => (int) $userSummary['inactive_users'],
                'suspended' => (int) $userSummary['suspended_users'],
                /**
                 * Employee records that have no login account — the literal
                 * "unlinked employee accounts" card on the module header.
                 */
                'unlinked_employee_accounts' => (int) $userSummary['unlinked_employees'],
                'accounts_without_employee' => (int) $userSummary['accounts_without_employee'],
            ],
        ];
    }

    /**
     * Late-report employees per department for the People Management header.
     *
     * @return list<array{department_id: int, department: string, total: int, with_late_reports: int}>
     */
    public function departmentBreakdown(): array
    {
        return collect(DB::table('departments')->pluck('name', 'id'))
            ->map(fn (string $name, int $id) => [
                'department_id' => $id,
                'department' => $name,
                'total' => Employee::query()->where('department_id', $id)->count(),
                'with_late_reports' => Employee::query()
                    ->where('department_id', $id)
                    ->whereHas('reports', fn ($q) => $q->where('is_late', true))
                    ->count(),
            ])
            ->values()
            ->all();
    }
}
