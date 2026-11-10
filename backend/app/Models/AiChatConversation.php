<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChatConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'context_summary', 'is_archived'];

    public function messages()
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
