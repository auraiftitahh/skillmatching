<?php

namespace Database\Factories;

use App\Models\CertificationBody;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingFactory extends Factory
{
    public function definition(): array
    {
        return ['certification_body_id' => CertificationBody::factory()->active(), 'title' => fake()->sentence(3), 'description' => 'Pelatihan operasional dengan capaian kemampuan yang jelas.', 'mode' => 'offline', 'duration_hours' => 8, 'weekly_hours' => 4, 'cost' => 0, 'source_url' => 'https://example.org/pelatihan', 'status' => 'active', 'version' => 1];
    }
}
