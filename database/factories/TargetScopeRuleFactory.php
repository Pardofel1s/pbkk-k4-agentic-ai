<?php

namespace Database\Factories;

use App\Models\Target;
use App\Models\TargetScopeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetScopeRule>
 */
class TargetScopeRuleFactory extends Factory
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
            'pola_domain' => '*.'.fake()->domainName(),
            'tipe' => fake()->randomElement(['include', 'exclude']),
        ];
    }
}
