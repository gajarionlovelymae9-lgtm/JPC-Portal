<?php

namespace App\Http\Resources\Ai;

use Illuminate\Http\Resources\Json\JsonResource;

class AiChatMessageResource extends JsonResource
{
    public function toArray($request)
    {
        if (! $this->isPublic()) {
            return [];
        }

return ['id' => $this->id, 'conversation_id' => $this->conversation_id, 'role' => $this->role->value ?? $this->role, 'content' => $this->content, 'intent' => $this->intent, 'tool_results' => $this->tool_results, 'created_at' => $this->created_at];
    }
}
