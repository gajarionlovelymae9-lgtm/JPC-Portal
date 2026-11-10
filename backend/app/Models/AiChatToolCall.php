<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChatToolCall extends Model
{
    use HasFactory;

    protected $fillable = ['ai_chat_message_id', 'tool_name', 'parameters', 'result', 'executed_at'];

    protected $casts = ['parameters' => 'array', 'result' => 'array', 'executed_at' => 'datetime'];

    public function message()
    {
        return $this->belongsTo(AiChatMessage::class, 'ai_chat_message_id');
    }
}
