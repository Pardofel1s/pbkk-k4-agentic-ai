<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
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
            'versi' => 1,
            'format' => fake()->randomElement(['pdf', 'html']),
            'file_path' => 'reports/'.fake()->uuid().'.pdf',
            'generated_at' => now(),
        ];
    }
}
