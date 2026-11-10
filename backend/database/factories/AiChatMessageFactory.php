<?php

namespace Database\Factories;

use App\Enums\AiMessageRole;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => AiChatConversation::factory(),
            'role' => AiMessageRole::User,
            'content' => fake()->sentence(8),
            'intent' => null,
            'tool_results' => null,
            'is_visible' => true,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AiMessageRole::Assistant,
            'is_visible' => true,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AiMessageRole::System,
            'is_visible' => false,
        ]);
    }
}
