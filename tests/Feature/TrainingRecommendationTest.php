<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Training;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\TrainingRecommendations;
use App\Support\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_uses_target_weighted_gap_and_excludes_unrelated_training(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $skill = $job->skills()->first();
        $user = User::factory()->create();
        $full = Training::factory()->published()->create(['cost' => 100]);
        $full->skills()->sync([$skill->id => ['target_level' => 2]]);
        $half = Training::factory()->published()->create(['cost' => 0]);
        $half->skills()->sync([$skill->id => ['target_level' => 1]]);
        $unrelated = Training::factory()->published()->create();
        $ranked = app(TrainingRecommendations::class)->rank($user, $job, Publication::trainings()->with('skills')->get());
        $this->assertSame([$full->id, $half->id], $ranked->pluck('id')->all());
        $this->assertEquals([100, 50], $ranked->pluck('gap_coverage')->all());
        $this->withoutVite()->actingAs($user)->get(route('applicant.trainings.index', ['job_id' => $job->id]))->assertOk()->assertSee('100,00% cakupan gap')->assertDontSee($unrelated->title);
        $this->get(route('applicant.trainings.index'))->assertOk()->assertDontSee('% cakupan gap');
        $this->assertDatabaseCount('user_skills', 0);
        $this->assertDatabaseCount('training_enrollments', 0);
    }

    public function test_complete_profile_has_no_gap_recommendation_and_ineligible_training_is_hidden(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $skill = $job->skills()->first();
        $user = User::factory()->create();
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $skill->id, 'level' => 2, 'self_reported_level' => 2]);
        $training = Training::factory()->published()->create();
        $training->skills()->sync([$skill->id => ['target_level' => 4]]);
        $this->assertCount(0, app(TrainingRecommendations::class)->rank($user, $job, Publication::trainings()->with('skills')->get()));
    }
}
