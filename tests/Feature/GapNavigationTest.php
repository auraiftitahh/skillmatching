<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Training;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GapNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_gap_and_training_navigation_keep_selected_position_context(): void
    {
        $job = JobVacancy::factory()->published()->create(['title' => 'Posisi Tujuan Khusus']);
        $skill = $job->skills()->first();
        $training = Training::factory()->published()->create();
        $this->actingAs(User::factory()->create())->get(route('applicant.gaps.show', $job))->assertOk()->assertSee($job->title)->assertSee('job_id='.$job->id)->assertSee('skill_id='.$skill->id);
        $this->get(route('applicant.trainings.index', ['job_id' => $job->id]))->assertOk()->assertSee('Posisi tujuan Anda')->assertSee($job->title);
        $this->get(route('applicant.trainings.show', ['training' => $training->id, 'job_id' => $job->id]))->assertOk()->assertSee('Posisi tujuan Anda')->assertSee('job_id='.$job->id);
        $this->get(route('applicant.matching.show', $job))->assertSee(route('applicant.gaps.show', $job));
    }

    public function test_without_target_no_global_gap_or_fabricated_coverage_is_shown(): void
    {
        $this->actingAs(User::factory()->create())->get(route('applicant.gaps.index'))->assertOk()->assertSee('Pilih posisi tujuan terlebih dahulu');
        $this->get(route('applicant.trainings.index'))->assertOk()->assertDontSee('Posisi tujuan Anda')->assertDontSee('cakupan gap');
    }

    public function test_complete_profile_and_unavailable_target_states_are_explicit(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $user = User::factory()->create();
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $job->skills()->first()->id, 'level' => 4, 'self_reported_level' => 4]);
        $this->actingAs($user)->get(route('applicant.gaps.show', $job))->assertOk()->assertSee('sudah memenuhi kebutuhan');
        $job->update(['status' => 'inactive']);
        $this->get(route('applicant.gaps.show', $job))->assertNotFound();
        $this->get(route('applicant.trainings.index', ['job_id' => $job->id]))->assertNotFound();
    }
}
