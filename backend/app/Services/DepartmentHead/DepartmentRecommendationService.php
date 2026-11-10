<?php

namespace App\Services\DepartmentHead;

use App\Models\AcademicYear;
use App\Models\Badge;
use App\Models\Employee;
use App\Models\EmployeeBadge;
use App\Models\User;
use App\Services\Admin\AdminBadgeManagementService;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Department Head recognition recommendations.
 *
 * A recommendation reuses the existing employee_badges award: it is created
 * as a pending award stamped with the recommending head, so HR's existing
 * verify/reject workflow (with self-approval guard and audit trail) reviews
 * it — no second recognition system and no duplicate tables.
 */
class DepartmentRecommendationService
{
    public function __construct(
        private readonly DepartmentHeadContextService $context,
        private readonly AdminBadgeManagementService $badges,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Active badge definitions a head may recommend; Admin-configured only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function badgeOptions(): array
    {
        return Badge::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description', 'icon', 'category', 'tier'])
            ->map(static fn (Badge $badge) => [
                'id' => $badge->id,
                'name' => $badge->name,
                'code' => $badge->code,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'category' => $badge->category,
                'tier' => $badge->tier,
            ])->all();
    }

    /**
     * Employees of the head's own department only — potential nominees.
     *
     * @return array<int, array<string, mixed>>
     */
    public function employeeOptions(User $user): array
    {
        $department = $this->context->resolveDepartment($user);

        return Employee::query()
            ->where('department_id', $department->id)
            ->with('position:id,name')
            ->orderBy('last_name')
            ->get()
            ->map(static fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position?->name,
            ])->all();
    }

    /**
     * The head's own submitted recommendations with their review status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listMine(User $user): array
    {
        $department = $this->context->resolveDepartment($user);

        return EmployeeBadge::query()
            ->recommended()
            ->where('recommended_by', $user->id)
            ->whereHas('employee', static fn ($q) => $q->where('department_id', $department->id))
            ->with(['employee.department', 'employee.position', 'badge', 'academicYear', 'verifier'])
            ->orderByDesc('recommended_at')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (EmployeeBadge $award) => [
                'id' => $award->id,
                'employee_id' => $award->employee_id,
                'employee_name' => $award->employee?->full_name,
                'employee_number' => $award->employee?->employee_number,
                'position' => $award->employee?->position?->name,
                'badge_id' => $award->badge_id,
                'badge_name' => $award->badge?->name,
                'badge_icon' => $award->badge?->icon,
                'badge_category' => $award->badge?->category,
                'badge_tier' => $award->badge?->tier,
                'recommended_at' => $award->recommended_at?->toIso8601String(),
                'recommendation_details' => $award->recommendation_details,
                'verification_status' => $award->verification_status,
                'verified_at' => $award->verified_at?->toIso8601String(),
                'reviewed_by' => $award->verifier?->name,
                'rejection_reason' => $award->rejection_reason,
            ])->all();
    }

    /**
     * Submit a recommendation for an employee in the head's own department.
     *
     * @param  array{employee_id: int|string, badge_id: int|string, details: string, period_start?: string|null, period_end?: string|null}  $data
     * @return array<string, mixed> The created pending award detail.
     */
    public function recommend(User $user, array $data): array
    {
        $department = $this->context->resolveDepartment($user);

        $employee = Employee::findOrFail((int) $data['employee_id']);
        // Hard department restriction: a head never reaches beyond their own
        // department, so other departments' records stay private.
        $this->context->authorizeEmployee($department, $employee);

        // A head cannot recognise themselves.
        if ((int) $user->employee?->id === (int) $employee->id) {
            throw ValidationException::withMessages([
                'employee_id' => ['You cannot recommend yourself for recognition.'],
            ]);
        }

        $badge = Badge::query()->where('is_active', true)->findOrFail((int) $data['badge_id']);

        $details = trim((string) $data['details']);
        if ($details === '') {
            throw ValidationException::withMessages([
                'details' => ['Supporting details are required.'],
            ]);
        }

        // Dedup: the unique index handles exact repeats and this guard turns
        // them into a friendly message — an employee can only hold one award
        // per badge per year/period, so an already-earned or already-pending
        // badge must not be recommended again.
        $exists = EmployeeBadge::query()
            ->where('employee_id', $employee->id)
            ->where('badge_id', $badge->id)
            ->when($data['period_start'] ?? null, static fn ($q, $start) => $q->whereDate('period_start', $start))
            ->when($data['period_end'] ?? null, static fn ($q, $end) => $q->whereDate('period_end', $end))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'badge_id' => ['This employee already holds (or has a pending) award for this badge in the selected period.'],
            ]);
        }

        $award = DB::transaction(function () use ($user, $badge, $employee, $details, $data): EmployeeBadge {
            $academicYearId = AcademicYear::query()
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->value('id') ?? AcademicYear::query()->orderByDesc('start_date')->value('id');

            $award = new EmployeeBadge([
                'employee_id' => $employee->id,
                'badge_id' => $badge->id,
                'academic_year_id' => $academicYearId,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'criteria_snapshot' => [
                    'source' => 'department_head_recommendation',
                    'reason' => $details,
                ],
            ]);
            $award->forceFill([
                'awarded_at' => now(),
                'verification_status' => 'pending',
                'recommended_by' => $user->id,
                'recommended_at' => now(),
                'recommendation_details' => $details,
                'awarded_by' => $user->id,
            ])->save();

            $this->activity->log(
                activityType: 'department_head.achievement.recommend',
                description: sprintf('Recommended %s for %s.', $employee->full_name, $badge->name),
                user: $user,
                subject: $award,
                metadata: ['details' => $details],
            );

            return $award;
        });

        return $this->badges->awardDetail($award->refresh());
    }
}
