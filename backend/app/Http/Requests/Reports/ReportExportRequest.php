<?php

namespace App\Http\Requests\Reports;

use App\Services\Reports\ReportFilterBag;
use Illuminate\Contracts\Validation\ValidationRule;

class ReportExportRequest extends ReportRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'format' => ['sometimes', 'nullable', 'in:'.implode(',', ReportFilterBag::FORMATS)],
        ];
    }
}