<?php

namespace Database\Factories;

use App\Models\JobVacancy;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobVacancyFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->jobTitle(), 'company' => fake()->company(), 'location' => 'Surabaya', 'description' => 'Posisi kerja formal dengan kebutuhan kompetensi yang jelas.', 'employment_type' => 'contract', 'source_url' => 'https://example.org/karier', 'source_name' => 'Situs perusahaan', 'fetched_at' => now()->toDateString(), 'expires_at' => now()->addMonth()->toDateString(), 'status' => 'draft'];
    }

    public function published(): static
    {
        return $this->state(['status' => 'active'])->afterCreating(function (JobVacancy $job): void {
            $skill = Skill::factory()->create();
            $job->skills()->attach($skill, ['minimum_level' => 2, 'importance' => 'required', 'weight' => 3, 'is_override' => false]);
        });
    }
}
