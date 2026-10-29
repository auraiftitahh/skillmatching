<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class WeightConfigurationFactory extends Factory
{
    public function definition(): array
    {
        return ['version' => (int) DB::table('weight_configurations')->max('version') + 1, 'required_weight' => 3, 'preferred_weight' => 1, 'related_factor' => 0.5, 'threshold' => 40, 'reason' => 'Konfigurasi skenario pengujian.'];
    }
}
