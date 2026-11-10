<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class HrAchievementFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->hasPermission('achievements.view') || $user->hasPermission('achievements.manage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'badge_id' => ['nullable', 'integer', 'exists:badges,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'category' => ['nullable', 'string', 'max:40'],
            'tier' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:pending,verified,rejected'],
            'verification_status' => ['nullable', 'string', 'in:pending,verified,rejected'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'format' => ['nullable', 'string', 'in:csv,xlsx,pdf'],
        ];
    }
}
