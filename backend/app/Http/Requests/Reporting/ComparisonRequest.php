<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;

class ComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'comparison_type' => 'required|string|in:employee,department,period',
            'ids' => 'required_if:comparison_type,employee,department|array|min:2|max:5',
            'ids.*' => 'integer',
            'period_a' => 'required_if:comparison_type,period|string',
            'period_b' => 'required_if:comparison_type,period|string',
            'year' => 'nullable|integer',
            'month' => 'nullable|integer',
        ];
    }
}
