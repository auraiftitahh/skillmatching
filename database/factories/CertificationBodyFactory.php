<?php

namespace Database\Factories;

use App\Models\CertificationBody;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationBody> */
class CertificationBodyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->institution(),
            'name' => fake()->company(),
            'contact' => fake()->safeEmail(),
            'status' => CertificationBody::STATUS_PENDING,
            'verified_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationBody::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);
    }
}
