<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
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
            'nomor' => fake()->unique()->numerify('CERT-######'),
            'issued_at' => now(),
            'due_date' => now()->addYear(),
        ];
    }
}
