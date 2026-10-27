<?php

namespace Tests\Feature;

use App\Models\JobTaxonomy;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_mapped_work_history_suggests_unique_skills_without_saving_them(): void
    {
        $user = User::factory()->create();
        $skill = Skill::factory()->create();
        $job = JobTaxonomy::factory()->create();
        $job->skills()->attach($skill);
        UserExperience::factory()->count(2)->create(['user_id' => $user->id, 'type' => 'work', 'job_taxonomy_id' => $job->id]);
        $this->actingAs($user)->get(route('applicant.skills.suggestions'))->assertOk()->assertViewHas('suggestions', fn ($s) => $s->count() === 1)->assertSee($skill->name);
        $this->assertDatabaseCount('user_skills', 0);
        $this->put(route('applicant.skills.update'), ['levels' => [$skill->id => 2]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_skills', ['user_id' => $user->id, 'skill_id' => $skill->id, 'level' => 2, 'verification_status' => 'self_reported']);
        $user->experiences()->delete();
        $this->get(route('applicant.skills.suggestions'))->assertSee('Belum ada saran');
        $this->assertDatabaseCount('user_skills', 1);
    }

    public function test_other_users_and_inactive_jobs_or_skills_do_not_contribute_suggestions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $skill = Skill::factory()->create();
        $job = JobTaxonomy::factory()->create();
        $job->skills()->attach($skill);
        UserExperience::factory()->create(['user_id' => $other->id, 'type' => 'work', 'job_taxonomy_id' => $job->id]);
        $this->actingAs($user)->get(route('applicant.skills.suggestions'))->assertViewHas('suggestions', fn ($s) => $s->isEmpty());
        UserExperience::factory()->create(['user_id' => $user->id, 'type' => 'work', 'job_taxonomy_id' => $job->id]);
        $job->update(['is_active' => false]);
        $this->get(route('applicant.skills.suggestions'))->assertViewHas('suggestions', fn ($s) => $s->isEmpty());
        $job->update(['is_active' => true]);
        $skill->update(['is_active' => false]);
        $this->get(route('applicant.skills.suggestions'))->assertViewHas('suggestions', fn ($s) => $s->isEmpty());
    }

    public function test_experience_form_can_select_job_and_other_history_types_clear_it(): void
    {
        $user = User::factory()->create();
        $job = JobTaxonomy::factory()->create();
        $data = ['type' => 'work', 'organization' => 'PT Uji', 'title' => 'Posisi nyata', 'is_current' => false, 'job_taxonomy_id' => $job->id];
        $this->actingAs($user)->get(route('applicant.experiences.create'))->assertOk()->assertSee('Jabatan acuan untuk saran');
        $this->post(route('applicant.experiences.store'), $data)->assertSessionHasNoErrors();
        $experience = $user->experiences()->firstOrFail();
        $this->assertSame($job->id, $experience->job_taxonomy_id);
        $data['type'] = 'education';
        $this->put(route('applicant.experiences.update', $experience), $data)->assertSessionHasNoErrors();
        $this->assertNull($experience->fresh()->job_taxonomy_id);
    }

    public function test_invalid_new_mapping_is_rejected_but_owned_historical_inactive_mapping_is_preserved(): void
    {
        $user = User::factory()->create();
        $job = JobTaxonomy::factory()->create(['is_active' => false]);
        $data = ['type' => 'work', 'organization' => 'PT Uji', 'title' => 'Posisi', 'is_current' => false, 'job_taxonomy_id' => $job->id];
        $this->actingAs($user)->post(route('applicant.experiences.store'), $data)->assertSessionHasErrors('job_taxonomy_id');
        $experience = UserExperience::factory()->create(['user_id' => $user->id, 'type' => 'work', 'job_taxonomy_id' => $job->id]);
        $this->put(route('applicant.experiences.update', $experience), $data)->assertSessionHasNoErrors();
        $data['job_taxonomy_id'] = 9999;
        $this->put(route('applicant.experiences.update', $experience), $data)->assertSessionHasErrors('job_taxonomy_id');
    }
}
