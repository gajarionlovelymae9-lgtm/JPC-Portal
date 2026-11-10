<?php

namespace App\Http\Resources\Ai;

use Illuminate\Http\Resources\Json\JsonResource;

class AiConversationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'context_summary' => $this->context_summary,
            'is_archived' => $this->is_archived,
            'message_count' => $this->messages()->count(),
            'last_message_at' => $this->messages()->latest()->value('created_at'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
