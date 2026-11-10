<?php

namespace App\Services\Hr;

use App\Models\AnnualPlan;
use App\Models\Employee;
use App\Models\EmployeeTarget;
use App\Models\User;
use App\Services\Admin\AdminKpiTargetService;
use App\Services\Audit\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * HR target management.
 *
 * Reads reuse {@see AdminKpiTargetService} — the same summary cards, filtered
 * pagination and decorated rows the Admin "KPI Targets" surface renders — so
 * progress/status derivation cannot diverge between portals. This service
 * therefore only owns the *write* half (create / edit / delete) plus the form
 * options the admin surface does not need.
 */
class HrTargetService
{
    public function __construct(
        private readonly AdminKpiTargetService $read,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters): array
    {
        $paginator = $this->read->paginate($filters);

        return [
            'summary' => $this->read->summary($filters),
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'options' => $this->options(),
        ];
    }

    /**
     * Form / toolbar options: filtering options from the shared read service,
     * plus the employees and annual plans a create/edit form needs.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $options = $this->read->options();

        $options['employees'] = Employee::query()
            ->with('department:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_number', 'department_id', 'employment_status'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'department_id' => $employee->department_id,
                'department_name' => $employee->department?->name,
                'status' => $employee->employment_status,
            ])->all();

        $options['annual_plans'] = AnnualPlan::query()
            ->with('academicYear:id,name')
            ->orderByDesc('id')
            ->get(['id', 'title', 'status', 'academic_year_id'])
            ->map(fn (AnnualPlan $plan) => [
                'id' => $plan->id,
                'title' => $plan->title,
                'status' => $plan->status,
                'academic_year' => $plan->academicYear?->name,
            ])->all();

        $options['stored_statuses'] = [
            ['value' => AdminKpiTargetService::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => AdminKpiTargetService::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => AdminKpiTargetService::STATUS_COMPLETED, 'label' => 'Completed'],
        ];

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $detail = $this->read->detail($id);

        if ($detail === null) {
            throw new DomainException('Target not found.');
        }

        return $detail;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?User $user): array
    {
        $target = DB::transaction(function () use ($data): EmployeeTarget {
            $target = new EmployeeTarget;
            $target->annual_plan_id = (int) $data['annual_plan_id'];
            $target->employee_id = (int) $data['employee_id'];
            $target->title = $data['title'];
            $target->description = $data['description'] ?? null;
            $target->target_value = $data['target_value'] ?? null;
            $target->target_unit = $data['target_unit'] ?? null;
            $target->start_date = $data['start_date'] ?? null;
            $target->end_date = $data['end_date'] ?? null;
            $target->status = $data['status'] ?? AdminKpiTargetService::STATUS_DRAFT;
            $target->save();

            return $target;
        });

        $this->activity->log(
            'target.created',
            "Created target '{$target->title}' (employee #{$target->employee_id}).",
            $user,
            $target,
        );

        return $this->show($target->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data, ?User $user): array
    {
        /** @var EmployeeTarget|null $target */
        $target = EmployeeTarget::query()->find($id);

        if ($target === null) {
            throw new DomainException('Target not found.');
        }

        $original = $target->only([
            'title', 'description', 'target_value', 'target_unit',
            'start_date', 'end_date', 'status', 'annual_plan_id',
        ]);

        $planChanged = isset($data['annual_plan_id'])
            && (int) $data['annual_plan_id'] !== $target->annual_plan_id;

        DB::transaction(function () use ($target, $data): void {
            if (array_key_exists('annual_plan_id', $data)) {
                $target->annual_plan_id = (int) $data['annual_plan_id'];
            }
            if (array_key_exists('title', $data)) {
                $target->title = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $target->description = $data['description'];
            }
            if (array_key_exists('target_value', $data)) {
                $target->target_value = $data['target_value'];
            }
            if (array_key_exists('target_unit', $data)) {
                $target->target_unit = $data['target_unit'];
            }
            if (array_key_exists('start_date', $data)) {
                $target->start_date = $data['start_date'];
            }
            if (array_key_exists('end_date', $data)) {
                $target->end_date = $data['end_date'];
            }
            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $target->status = $data['status'];
            }
            $target->save();
        });

        $this->activity->log(
            'target.updated',
            "Updated target '{$target->title}' (employee #{$target->employee_id}).",
            $user,
            $target,
            ['before' => $original, 'after' => $target->only(array_keys($original))],
        );

        return $this->show($target->id);
    }

    /**
     * Soft delete — the planning chain and report joins all read
     * `deleted_at`, so history survives the removal (mirrors the model's
     * SoftDeletes contract).
     */
    public function delete(int $id, ?User $user): void
    {
        /** @var EmployeeTarget|null $target */
        $target = EmployeeTarget::query()->find($id);

        if ($target === null) {
            throw new DomainException('Target not found.');
        }

        $title = $target->title;
        $employeeId = (int) $target->employee_id;
        $linkedTasks = $target->tasks()->count();

        $target->delete();

        $this->activity->log(
            'target.deleted',
            "Deleted target '{$title}' (employee #{$employeeId}).",
            $user,
            $target,
            ['employee_id' => $employeeId, 'linked_tasks' => $linkedTasks],
        );
    }
}
