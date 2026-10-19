<?php

namespace Database\Factories;

use App\Models\Target;
use App\Models\TargetTestAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetTestAccount>
 */
class TargetTestAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'target_id' => Target::factory(),
            'label_role' => fake()->randomElement(['nasabah', 'mitra', 'admin']),
            'username' => fake()->userName(),
            'encrypted_password' => 'password',
        ];
    }
}
