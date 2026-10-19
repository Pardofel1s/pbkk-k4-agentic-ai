<?php

namespace Database\Factories;

use App\Models\Finding;
use App\Models\Scan;
use App\Models\VulnerabilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scan_id' => Scan::factory(),
            'vulnerability_type_id' => VulnerabilityType::factory(),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'url_terdampak' => fake()->url(),
            'parameter' => fake()->word(),
            'proof' => fake()->sentence(),
            'rekomendasi' => fake()->sentence(),
            'status' => fake()->randomElement(['open', 'fixed']),
        ];
    }
}
