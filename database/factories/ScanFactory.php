<?php

namespace Database\Factories;

use App\Models\Scan;
use App\Models\ScanProfile;
use App\Models\Target;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scan>
 */
class ScanFactory extends Factory
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
            'scan_profile_id' => ScanProfile::factory(),
            'status' => 'completed',
            'progress' => 100,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ];
    }
}
