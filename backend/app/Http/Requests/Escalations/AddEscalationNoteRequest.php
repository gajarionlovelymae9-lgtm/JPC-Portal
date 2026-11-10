<?php

namespace App\Http\Requests\Escalations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an internal note added by Administration while reviewing an
 * escalation. Internal notes are never exposed to the Department Head.
 */
class AddEscalationNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
            'is_internal' => ['nullable', 'boolean'],
        ];
    }
}
