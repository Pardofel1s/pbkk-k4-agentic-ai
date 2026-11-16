<?php

namespace Database\Factories;

use App\Models\ChatLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatLog>
 */
class ChatLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $promptTokens = fake()->numberBetween(10, 200);
        $completionTokens = fake()->numberBetween(20, 600);

        return [
            'user_id' => User::factory(),
            'prompt' => fake()->sentence(),
            'ai_response' => fake()->paragraph(),
            'model_used' => 'qwen3:14b',
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
        ];
    }
}
