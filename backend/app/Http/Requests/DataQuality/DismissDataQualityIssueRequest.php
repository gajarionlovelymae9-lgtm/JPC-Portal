<?php

namespace App\Http\Requests\DataQuality;

use Illuminate\Foundation\Http\FormRequest;

class DismissDataQualityIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:2000',
        ];
    }
}
