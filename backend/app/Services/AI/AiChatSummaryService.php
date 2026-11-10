<?php

namespace App\Services\AI;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatSummary;
use App\Models\User;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiChatSummaryService
{
    public function __construct(private readonly AiProvider $provider) {}

    /**
     * @return array<string, mixed>
     */
    public function save(User $user, array $data): array
    {
        $conversation = $this->ownedConversation($user, (int) $data['conversation_id']);
        $content = $this->resolveContent($user, $conversation, $data);

        $summary = AiChatSummary::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'related_task_id' => $data['related_task_id'] ?? null,
            'title' => $this->title($data['title'] ?? null, $content),
            'summary_type' => $data['summary_type'] ?? $this->inferType($content),
            'summary_content' => $content,
            'metadata' => [
                'source' => $data['source'] ?? 'assistant_message',
                'assistant_message_id' => $data['assistant_message_id'] ?? null,
            ],
        ]);

        return $this->shape($summary->loadMissing('conversation'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(User $user, ?int $conversationId = null): array
    {
        $query = AiChatSummary::query()
            ->where('user_id', $user->id)
            ->when($conversationId, fn ($q) => $q->where('conversation_id', $conversationId))
            ->latest('created_at')
            ->limit(100);

        return $query->get()->map(fn (AiChatSummary $summary) => $this->shape($summary))->all();
    }

    public function findOwned(User $user, int $summaryId): AiChatSummary
    {
        $summary = AiChatSummary::query()->find($summaryId);

        if ($summary === null || (int) $summary->user_id !== (int) $user->id) {
            throw new AuthorizationException('You do not have access to this saved summary.');
        }

        return $summary;
    }

    public function delete(User $user, int $summaryId): void
    {
        $this->findOwned($user, $summaryId)->delete();
    }

    private function ownedConversation(User $user, int $conversationId): AiChatConversation
    {
        $conversation = AiChatConversation::query()->find($conversationId);

        if ($conversation === null || (int) $conversation->user_id !== (int) $user->id) {
            throw new AuthorizationException('You do not have access to this conversation.');
        }

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveContent(User $user, AiChatConversation $conversation, array $data): string
    {
        $explicit = trim((string) ($data['summary_content'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $messageId = $data['assistant_message_id'] ?? null;
        if ($messageId) {
            $message = AiChatMessage::query()
                ->where('id', $messageId)
                ->where('conversation_id', $conversation->id)
                ->first();

            if ($message === null) {
                throw new AuthorizationException('The selected message could not be found in this conversation.');
            }

            $content = trim((string) $message->content);
            if ($content !== '') {
                return $content;
            }
        }

        // Fallback: save the latest assistant response in the conversation.
        $latest = AiChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('is_visible', true)
            ->latest('id')
            ->first();

        if ($latest && trim((string) $latest->content) !== '') {
            return trim((string) $latest->content);
        }

        // Last resort: ask provider to summarize conversation from actual messages.
        return $this->summarizeConversation($conversation);
    }

    private function summarizeConversation(AiChatConversation $conversation): string
    {
        $messages = AiChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_visible', true)
            ->latest('id')
            ->limit(20)
            ->get(['role', 'content'])
            ->reverse();

        $transcript = $messages->map(function ($m) {
            $role = is_object($m->role) ? $m->role->value : (string) $m->role;

            return ($role === 'assistant' ? 'Assistant' : 'Employee').': '.Str::limit(trim((string) $m->content), 500);
        })->implode("\n");

        if (trim($transcript) === '') {
            return 'No conversation content is available to save as a summary.';
        }

        try {
            $completion = $this->provider->complete(
                'Create a concise professional summary using ONLY the conversation content provided. Do not fabricate activities, accomplishments, tasks, dates, or outcomes. If details are missing, say so clearly. Return plain text.',
                "Conversation:\n".$transcript,
                new AiRequest(
                    model: (string) (config('ai_reports.ai.model') ?? config('ai.model', 'gpt-4o-mini')),
                    maxTokens: 800,
                    temperature: 0.2,
                    expectJson: false,
                    metadata: ['analysis_type' => 'chat_summary'],
                ),
            );

            $text = trim($completion->text);
            $decoded = json_decode($text, true);

            return is_array($decoded) ? $transcript : ($text !== '' ? $text : $transcript);
        } catch (Throwable $e) {
            Log::notice('AI chat summary fallback used', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);

            return "Summary based on conversation:\n\n".$transcript;
        }
    }

    private function title(?string $title, string $content): string
    {
        $title = trim((string) $title);
        if ($title !== '') {
            return Str::limit($title, 120);
        }

        $firstLine = trim(strtok($content, "\n") ?: $content);

        return Str::limit(preg_replace('/^[#*\-\s]+/', '', $firstLine) ?: 'AI Summary', 120);
    }

    private function inferType(string $content): string
    {
        $c = strtolower($content);
        if (str_contains($c, 'monthly')) {
            return 'monthly';
        }
        if (str_contains($c, 'weekly')) {
            return 'weekly';
        }
        if (str_contains($c, 'daily')) {
            return 'daily';
        }

        return 'accomplishment';
    }

    /**
     * @return array<string, mixed>
     */
    public function shape(AiChatSummary $summary): array
    {
        return [
            'id' => $summary->id,
            'conversation_id' => $summary->conversation_id,
            'related_task_id' => $summary->related_task_id,
            'title' => $summary->title,
            'summary_type' => $summary->summary_type,
            'summary_content' => $summary->summary_content,
            'metadata' => $summary->metadata,
            'created_at' => optional($summary->created_at)->toIso8601String(),
            'updated_at' => optional($summary->updated_at)->toIso8601String(),
        ];
    }
}
