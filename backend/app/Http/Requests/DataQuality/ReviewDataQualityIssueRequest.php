<?php

namespace App\Http\Requests\DataQuality;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDataQualityIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
