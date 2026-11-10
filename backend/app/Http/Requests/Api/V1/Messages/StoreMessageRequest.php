<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a "send message" payload.
 *
 * A message targets either an existing conversation (`conversation_id`, used
 * for replies and group threads) or a new/ongoing direct thread
 * (`recipient_id`). At least one of them must be present, and a message must
 * carry either body text or an attachment. Files are restricted to the
 * institutional document/image types and a 10 MB ceiling.
 */
class StoreMessageRequest extends FormRequest
{
    /** Allowed attachment extensions (PDF, Word, Excel, and images). */
    public const ATTACHMENT_MIMES = 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png';

    /** Attachment ceiling in kilobytes (10 MB). */
    public const ATTACHMENT_MAX_KB = 10240;

    public function authorize(): bool
    {
        return true; // participant authorization is enforced by the ConversationPolicy.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'reply_to_message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'client_token' => ['nullable', 'string', 'max:64'],
            'attachment' => ['nullable', 'file', 'mimes:'.self::ATTACHMENT_MIMES, 'max:'.self::ATTACHMENT_MAX_KB],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('conversation_id') && ! $this->filled('recipient_id')) {
                $validator->errors()->add('recipient_id', 'Select a recipient or an existing conversation.');
            }

            if (blank($this->input('body')) && ! $this->hasFile('attachment')) {
                $validator->errors()->add('body', 'Enter a message or attach a file.');
            }
        });
    }
}
