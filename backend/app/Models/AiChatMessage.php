<?php
namespace App\Models;
use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class AiChatMessage extends Model {
    use HasFactory;
    protected $fillable = ['conversation_id', 'role', 'content', 'intent', 'tool_results', 'is_visible'];
    protected $casts = [ 'role' => AiMessageRole::class, 'tool_results' => 'array', 'is_visible' => 'boolean' ];
    public function conversation() { return $this->belongsTo(AiChatConversation::class, 'conversation_id'); }
    public function toolCalls() { return $this->hasMany(AiChatToolCall::class, 'ai_chat_message_id'); }
    public function isPublic() { return $this->is_visible && $this->role !== AiMessageRole::SYSTEM; }
}