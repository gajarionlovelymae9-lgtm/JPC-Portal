<?php

namespace App\Http\Requests\Escalations;

use App\Enums\EscalationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an Administration review action that moves an escalation through
 * the workflow and (optionally) records the resolution.
 */
class UpdateEscalationStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(EscalationStatus::values())],
            'resolution_notes' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('status'),
                    [EscalationStatus::Resolved->value, EscalationStatus::Rejected->value],
                    true,
                )),
            ],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
