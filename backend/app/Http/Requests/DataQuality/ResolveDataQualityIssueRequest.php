<?php

namespace App\Http\Requests\DataQuality;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDataQualityIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'notes' => 'required|string|min:3|max:2000',
        ];
    }
}
