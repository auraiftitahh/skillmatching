<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserExperience> */
class UserExperienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'type' => 'work',
            'organization' => fake()->company(), 'title' => fake()->jobTitle(),
            'start_date' => fake()->dateTimeBetween('-4 years', '-2 years')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
            'is_current' => false, 'description' => fake()->paragraph(),
        ];
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => ['is_current' => true, 'end_date' => null]);
    }
}
