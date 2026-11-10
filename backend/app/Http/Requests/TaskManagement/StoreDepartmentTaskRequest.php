<?php

namespace App\Http\Requests\TaskManagement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the department-head "create & assign" workflow.
 *
 * Assignees are required because the department-head flow always produces an
 * assigned task, and the deadline must not precede the start date.
 */
class StoreDepartmentTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['required', 'date', 'after_or_equal:start_date'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'target_unit' => ['nullable', 'string', 'max:50'],
            'assigned_employee_ids' => ['required', 'array', 'min:1'],
            'assigned_employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_employee_ids.required' => 'Select at least one employee to assign this task to.',
            'assigned_employee_ids.min' => 'Select at least one employee to assign this task to.',
            'deadline.after_or_equal' => 'The deadline must be on or after the start date.',
        ];
    }
}
