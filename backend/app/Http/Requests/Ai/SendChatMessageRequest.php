<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => 'nullable|exists:ai_chat_conversations,id',
            'message' => 'required|string|min:1|max:2000',
            'language_preference' => 'nullable|string|in:auto,english,filipino,tagalog,taglish',
        ];
    }
}
