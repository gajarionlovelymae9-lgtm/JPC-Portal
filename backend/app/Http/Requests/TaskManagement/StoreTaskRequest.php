<?php

namespace App\Http\Requests\TaskManagement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'department_id' => ['required', 'exists:departments,id'],
            'annual_plan_id' => ['nullable', 'exists:annual_plans,id'],
        ];
    }
}
