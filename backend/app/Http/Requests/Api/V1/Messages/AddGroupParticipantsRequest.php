<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates adding one or more members to an existing group conversation.
 */
class AddGroupParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // membership rules are enforced by ConversationPolicy.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
