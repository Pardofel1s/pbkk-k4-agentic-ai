<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commentable_type' => (new Finding())->getMorphClass(),
            'commentable_id' => Finding::factory(),
            'isi' => fake()->sentence(),
        ];
    }
}
