<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|string|in:employee_progress,department_performance,institution_performance,monthly_performance,achievement_summary,late_reports,pending_reports,targets_summary,data_quality',
            'format' => 'required|string|in:csv,excel,pdf',
            'period_type' => 'nullable|string|in:daily,weekly,monthly,quarterly,yearly,custom',
            'year' => 'nullable|integer',
            'month' => 'nullable|integer',
            'quarter' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'department_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'filters' => 'nullable|array',
        ];
    }
}
