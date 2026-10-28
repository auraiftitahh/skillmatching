<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobVacancyAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function payload(Skill $skill): array
    {
        return ['title' => 'Staf Gudang', 'company' => 'PT Contoh', 'location' => 'Surabaya', 'description' => 'Mengelola operasional gudang.', 'employment_type' => 'contract', 'source_url' => 'https://example.org/karier', 'fetched_at' => now()->toDateString(), 'expires_at' => now()->addMonth()->toDateString(), 'requirements' => [['skill_id' => $skill->id, 'minimum_level' => 3, 'importance' => 'required', 'weight' => null]]];
    }

    public function test_admin_creates_draft_job_with_requirements_and_can_update_it(): void
    {
        $skill = Skill::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/lowongan')->assertOk();
        $this->get(route('admin.jobs.create'))->assertOk()->assertSee($skill->name);
        $data = $this->payload($skill);
        $data['status'] = 'active';
        $this->post(route('admin.jobs.store'), $data)->assertSessionHasNoErrors();
        $job = JobVacancy::firstOrFail();
        $this->assertSame('draft', $job->status);
        $this->assertDatabaseHas('job_required_skills', ['job_vacancy_id' => $job->id, 'skill_id' => $skill->id, 'minimum_level' => 3, 'weight' => 3, 'is_override' => false]);
        $data['requirements'][0]['weight'] = 5;
        $this->put(route('admin.jobs.update', $job), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('job_required_skills', ['job_vacancy_id' => $job->id, 'weight' => 5, 'is_override' => true]);
        $this->get(route('admin.jobs.edit', $job))->assertOk();
    }

    public function test_invalid_duplicate_or_inactive_requirements_and_unsafe_sources_are_rejected_atomically(): void
    {
        $skill = Skill::factory()->create();
        $data = $this->payload($skill);
        $this->actingAs(User::factory()->admin()->create());
        $data['requirements'][] = $data['requirements'][0];
        $this->post(route('admin.jobs.store'), $data)->assertSessionHasErrors('requirements.0.skill_id');
        $this->assertDatabaseCount('job_vacancies', 0);
        $data = $this->payload($skill);
        $data['source_url'] = 'javascript:alert(1)';
        $this->post(route('admin.jobs.store'), $data)->assertSessionHasErrors('source_url');
        $skill->update(['is_active' => false]);
        $this->post(route('admin.jobs.store'), $this->payload($skill))->assertSessionHasErrors('requirements.0.skill_id');
        $this->assertDatabaseCount('job_vacancies', 0);
    }

    public function test_applicant_and_institution_cannot_manage_vacancies(): void
    {
        $skill = Skill::factory()->create();
        foreach ([User::factory()->create(), User::factory()->institution()->create()] as $user) {
            $this->actingAs($user)->get('/admin/lowongan')->assertForbidden();
            $this->post(route('admin.jobs.store'), $this->payload($skill))->assertForbidden();
        }
        $this->assertDatabaseCount('job_vacancies',0);
    }
}
