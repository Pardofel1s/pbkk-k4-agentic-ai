<?php

namespace Database\Factories;

use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Target>
 */
class TargetFactory extends Factory
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
            'nama' => fake()->company(),
            'base_url' => 'https://staging.'.fake()->domainName(),
            'lingkungan' => fake()->randomElement(['staging', 'uat']),
            'status_verifikasi' => fake()->randomElement(['pending', 'verified', 'rejected']),
        ];
    }
}
