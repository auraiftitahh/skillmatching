<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_ctas_keep_context_and_result_explains_unchanged_score(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $job = JobVacancy::factory()->published()->create();
        $training = Training::factory()->published()->create();
        $this->actingAs($user);
        $this->get(route('applicant.trainings.show', ['training' => $training->id, 'job_id' => $job->id]))->assertOk()->assertSee(route('applicant.simulations.create', ['training_id' => $training->id, 'job_id' => $job->id]))->assertSee('Tambahkan ke rencana belajar');
        $this->post(route('applicant.simulations.calculate'), ['job_id' => $job->id, 'training_ids' => [$training->id]])->assertOk()->assertSee('Skor tidak berubah.')->assertSee('Kontribusi tetap');
        $training->skills()->sync([$job->skills()->first()->id => ['target_level' => 2]]);
        $this->post(route('applicant.simulations.calculate'), ['job_id' => $job->id, 'training_ids' => [$training->id]])->assertOk()->assertSee('100,00 poin')->assertSee('Kontribusi meningkat');
        $this->assertDatabaseCount('user_skills', 0);
        $this->assertDatabaseCount('training_enrollments', 0);
    }
}
