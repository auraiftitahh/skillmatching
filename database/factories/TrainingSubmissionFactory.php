<?php

namespace Database\Factories;

use App\Models\CertificationBody;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return ['certification_body_id' => CertificationBody::factory()->active(), 'status' => 'draft', 'payload' => ['title' => 'Forklift Dasar', 'description' => 'Praktik pengoperasian forklift secara aman.', 'mode' => 'offline', 'duration_hours' => 8, 'weekly_hours' => 4, 'cost' => 0, 'source_url' => 'https://example.org/forklift', 'outcomes' => [['skill_id' => null, 'new_name' => 'Operasi Forklift', 'category' => 'Logistik', 'description' => 'Mengoperasikan forklift sesuai prosedur.', 'target_level' => 2]]]];
    }
}
