<?php

namespace App\Http\Requests\Hr;

use App\Services\Admin\AdminKpiTargetService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for the HR target management surface (store + update).
 *
 * Rules mirror the `employee_targets` migration exactly: a target is anchored
 * to an annual plan and an employee, the measurable value is optional but must
 * be non-negative when given, and the stored status stays within the
 * draft/active/completed lifecycle (`EmployeeTarget` scopes only ever read
 * those three).
 */
class HrTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'annual_plan_id' => ['required', 'integer', 'exists:annual_plans,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'target_unit' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in([AdminKpiTargetService::STATUS_DRAFT, AdminKpiTargetService::STATUS_ACTIVE, AdminKpiTargetService::STATUS_COMPLETED])],
        ];

        // On update the linked employee is immutable: re-parenting a target
        // silently rewrites the achievement history of two people. The UI does
        // not offer it and the API refuses it.
        if (! $this->isMethod('post')) {
            unset($rules['employee_id']);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Select the employee assigned to this target.',
            'annual_plan_id.required' => 'Select the annual plan (academic year) this target belongs to.',
            'title.required' => 'Give the target a title.',
            'target_value.min' => 'The target value cannot be negative.',
            'end_date.after_or_equal' => 'The due date cannot fall before the start date.',
            'status.in' => 'A stored status can only be draft, active or completed.',
        ];
    }

    /**
     * A target without dates cannot express a period; require at least one so
     * the dashboard's "Target Period" and "missed" derivations always have a
     * real range to work from.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('start_date') && ! $this->filled('end_date')) {
                $validator->errors()->add(
                    'start_date',
                    'Set a start date, a due date, or both — a target needs a period.',
                );
            }
        });
    }
}
