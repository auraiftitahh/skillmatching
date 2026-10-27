<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobTaxonomyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return ['name' => $name, 'normalized_name' => Skill::normalize($name), 'description' => 'Pemetaan kompetensi untuk jabatan.', 'is_active' => true];
    }
}
