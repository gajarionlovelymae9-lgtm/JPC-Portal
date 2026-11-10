<?php

namespace App\Http\Resources\Ai;

use Illuminate\Http\Resources\Json\JsonResource;

class AiChatResponseResource extends JsonResource
{
    public function toArray($request)
    {
        $message = $this['message'] ?? null;

        return [
            'conversation_id' => $this['conversation_id'] ?? ($message?->conversation_id),
            'conversation' => isset($this['conversation'])
                ? new AiConversationResource($this['conversation'])
                : null,
            'message' => new AiChatMessageResource($message),
            'intent' => $this['intent'] ?? null,
            'period' => $this['period'] ?? null,
            'results' => $this['results'] ?? null,
            'actions' => $this['actions'] ?? [],
        ];
    }
}
