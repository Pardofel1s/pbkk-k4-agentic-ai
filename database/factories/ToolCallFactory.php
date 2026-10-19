<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\Tool;
use App\Models\ToolCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolCall>
 */
class ToolCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'tool_id' => Tool::factory(),
            'parameter' => ['url' => fake()->url()],
            'hasil' => ['ok' => true],
            'status' => fake()->randomElement(['success', 'failed']),
            'error_msg' => null,
        ];
    }
}
