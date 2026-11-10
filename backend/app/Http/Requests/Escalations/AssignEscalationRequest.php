<?php

namespace App\Http\Requests\Escalations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates assigning a responsible administrator to an escalation.
 */
class AssignEscalationRequest extends FormRequest
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
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
