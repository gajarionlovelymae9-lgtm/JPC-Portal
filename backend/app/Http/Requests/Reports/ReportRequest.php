<?php

namespace App\Http\Requests\Reports;

use App\Services\Reports\ReportFilterBag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Filters shared by every report endpoint. Values here are re-validated
     * server-side even though the UI constrains them; the baggage is
     * normalized into ReportFilterBag by the controller.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
            'month' => ['sometimes', 'nullable', 'integer', 'between:1,12'],
            'year' => ['sometimes', 'nullable', 'integer', 'between:2000,2100'],
            'status' => ['sometimes', 'nullable', 'in:'.implode(',', ReportFilterBag::STATUSES)],
            'priority' => ['sometimes', 'nullable', 'in:'.implode(',', ReportFilterBag::PRIORITIES)],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'in:'.implode(',', ReportFilterBag::SORTABLE)],
            'direction' => ['sometimes', 'nullable', 'in:'.implode(',', ReportFilterBag::DIRECTIONS)],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.config('reports.max_per_page', 100)],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.config('reports.top_performers_limit_max', 10)],
            'scope' => ['sometimes', 'nullable', 'in:'.implode(',', ReportFilterBag::SCOPES)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from_date');
            $to = $this->input('to_date');

            if ($from !== null && $to !== null && strtotime((string) $from) > strtotime((string) $to)) {
                $validator->errors()->add('to_date', 'The "to" date must be on or after the "from" date.');
            }
        });
    }
}