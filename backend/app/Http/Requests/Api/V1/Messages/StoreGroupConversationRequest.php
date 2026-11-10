<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the creation of a group conversation.
 *
 * The creator is always added as a group administrator; `participant_ids`
 * lists the other authorized users to invite.
 */
class StoreGroupConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // module access is gated by route middleware + policy.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:16'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
