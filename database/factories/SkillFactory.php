<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Skill> */
class SkillFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(3, true), 'category' => 'Logistik',
            'description' => 'Kemampuan operasional yang memiliki cakupan jelas.', 'is_active' => true];
    }
}
