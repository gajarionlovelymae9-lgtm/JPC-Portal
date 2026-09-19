<?php

namespace App\Http\Requests\TaskManagement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'required', 'string'],
            'status' => ['sometimes', 'required', 'string'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'annual_plan_id' => ['nullable', 'exists:annual_plans,id'],
            'department_plan_id' => ['nullable', 'exists:department_plans,id'],
            'department_target_id' => ['nullable', 'exists:department_targets,id'],
            'employee_target_id' => ['nullable', 'exists:employee_targets,id'],
        ];
    }
}
