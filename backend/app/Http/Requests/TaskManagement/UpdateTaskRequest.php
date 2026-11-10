<?php

namespace App\Http\Requests\TaskManagement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status' => ['sometimes', 'required', Rule::in([
                'draft', 'assigned', 'in_progress', 'submitted',
                'under_review', 'completed', 'rejected', 'cancelled',
            ])],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'annual_plan_id' => ['nullable', 'exists:annual_plans,id'],
            'department_plan_id' => ['nullable', 'exists:department_plans,id'],
            'department_target_id' => ['nullable', 'exists:department_targets,id'],
            'employee_target_id' => ['nullable', 'exists:employee_targets,id'],
            'required_output' => ['nullable', 'string', 'max:5000'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'target_unit' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'The due date must be on or after the start date.',
        ];
    }
}
