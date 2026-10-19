<?php

namespace Database\Factories;

use App\Models\ScanProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanProfile>
 */
class ScanProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->words(2, true),
            'tipe' => fake()->randomElement(['quick', 'full', 'auth']),
            'deskripsi' => fake()->sentence(),
            'pengaturan' => ['max_depth' => 3, 'rate_limit' => 5],
        ];
    }
}
