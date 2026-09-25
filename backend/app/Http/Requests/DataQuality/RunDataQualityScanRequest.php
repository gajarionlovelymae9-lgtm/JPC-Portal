<?php

namespace App\Http\Requests\DataQuality;

use Illuminate\Foundation\Http\FormRequest;

class RunDataQualityScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'scan_type' => 'nullable|string|in:full,incremental,department,entity',
            'department_id' => 'nullable|integer|exists:departments,id',
            'employee_id' => 'nullable|integer|exists:employees,id',
        ];
    }
}
