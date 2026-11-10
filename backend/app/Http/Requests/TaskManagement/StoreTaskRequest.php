<?php

namespace App\Http\Requests\TaskManagement;

use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status' => ['nullable', Rule::in(['draft', 'assigned'])],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'department_id' => ['required', 'exists:departments,id'],
            'annual_plan_id' => ['nullable', 'exists:annual_plans,id'],
            'department_plan_id' => ['nullable', 'exists:department_plans,id'],
            'department_target_id' => ['nullable', 'exists:department_targets,id'],
            'employee_target_id' => ['nullable', 'exists:employee_targets,id'],
            'required_output' => ['nullable', 'string', 'max:5000'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'target_unit' => ['nullable', 'string', 'max:50'],
            'assigned_employee_ids' => ['nullable', 'array'],
            'assigned_employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'The due date must be on or after the start date.',
            'assigned_employee_ids.*.exists' => 'One of the selected employees no longer exists.',
        ];
    }
}
