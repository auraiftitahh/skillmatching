<?php

namespace Database\Factories;

use App\Models\CertificationBody;
use App\Models\Skill;
use App\Models\Training;
use App\Models\TrainingSubmission;
use App\Models\User;
use App\Services\TrainingSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingFactory extends Factory
{
    public function definition(): array
    {
        return ['certification_body_id' => CertificationBody::factory()->active(), 'title' => fake()->sentence(3), 'description' => 'Pelatihan operasional dengan capaian kemampuan yang jelas.', 'mode' => 'offline', 'duration_hours' => 8, 'weekly_hours' => 4, 'cost' => 0, 'source_url' => 'https://example.org/pelatihan', 'status' => 'active', 'version' => 1];
    }

    public function published(): static
    {
        return $this->afterCreating(function (Training $training): void {
            $skill = Skill::factory()->create();
            $training->skills()->attach($skill, ['target_level' => 2]);
            $payload = $training->only(['title', 'description', 'mode', 'duration_hours', 'weekly_hours', 'cost', 'source_url']);
            $payload['outcomes'] = [['skill_id' => $skill->id, 'target_level' => 2, 'resolved_skill_id' => $skill->id]];
            TrainingSubmission::create(['certification_body_id' => $training->certification_body_id, 'training_id' => $training->id, 'status' => 'approved', 'payload' => $payload, 'review_note' => 'Data uji telah ditinjau.', 'reviewed_by' => User::factory()->admin()->create()->id, 'reviewed_at' => now(), 'submitted_at' => now()]);
            TrainingSnapshot::preserve($training);
        });
    }
}
