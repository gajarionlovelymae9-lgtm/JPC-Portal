<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'period_type' => 'nullable|string|in:daily,weekly,monthly,quarterly,yearly,custom',
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'quarter' => 'nullable|integer|min:1|max:4',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department_id' => 'nullable|integer|exists:departments,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'status' => 'nullable|string',
            'rating' => 'nullable|string',
            'metric' => 'nullable|string|in:productivity,completion_rate,on_time_rate,approval_rate,target_achievement',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }
}
