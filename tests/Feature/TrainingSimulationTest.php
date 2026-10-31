<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Training;
use App\Models\User;
use App\Services\TrainingSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_uses_same_configuration_without_persisting_hypothetical_levels(): void
    {
        $user = User::factory()->create();
        $job = JobVacancy::factory()->published()->create();
        $skill = $job->skills()->first();
        $training = Training::factory()->published()->create();
        $training->skills()->sync([$skill->id => ['target_level' => 2]]);
        $result = app(TrainingSimulation::class)->run($user, $job, [$training->id]);
        $this->assertSame(0.0, $result['before']['score']);
        $this->assertSame(100.0, $result['after']['score']);
        $this->assertSame($result['before']['configuration_id'], $result['after']['configuration_id']);
        $this->withoutVite()->actingAs($user)->get(route('applicant.simulations.create', ['job_id' => $job->id, 'training_id' => $training->id]))->assertOk()->assertSee($training->title);
        $this->post(route('applicant.simulations.calculate'), ['job_id' => $job->id, 'training_ids' => [$training->id]])->assertOk()->assertSee('bukan profil aktual');
        foreach (['user_skills', 'skill_evidence', 'training_enrollments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_selection_rejects_duplicates_too_many_and_unpublished_records(): void
    {
        $this->withoutVite()->actingAs(User::factory()->create());
        $job = JobVacancy::factory()->published()->create();
        $training = Training::factory()->published()->create();
        $hidden = Training::factory()->create();
        $this->post(route('applicant.simulations.calculate'), ['job_id' => $job->id, 'training_ids' => [$training->id, $training->id]])->assertSessionHasErrors('training_ids.0');
        $this->post(route('applicant.simulations.calculate'), ['job_id' => $job->id, 'training_ids' => [1, 2, 3, 4]])->assertSessionHasErrors('training_ids');
        $this->post(route('applicant.simulations.calculate'), ['job_id' => $job->id, 'training_ids' => [$hidden->id]])->assertNotFound();
        $job->update(['status' => 'inactive']);
        $this->get(route('applicant.simulations.create', ['job_id' => $job->id]))->assertNotFound();
    }
}
