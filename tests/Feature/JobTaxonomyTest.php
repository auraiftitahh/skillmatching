<?php

namespace Tests\Feature;

use App\Models\JobTaxonomy;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_map_jobs_to_existing_skills_without_creating_applicant_skills(): void
    {
        $skill = Skill::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/jabatan')->assertOk();
        $this->get(route('admin.taxonomies.create'))->assertOk()->assertSee($skill->name);
        $this->post(route('admin.taxonomies.store'), ['name' => ' Staf   Gudang ', 'is_active' => 1, 'skills' => [$skill->id]])->assertSessionHasNoErrors();
        $job = JobTaxonomy::firstOrFail();
        $this->assertSame('Staf Gudang', $job->name);
        $this->assertSame([$skill->id], $job->skills()->pluck('skills.id')->all());
        $this->assertDatabaseCount('user_skills', 0);
        $this->get(route('admin.taxonomies.edit', $job))->assertOk();
        $this->put(route('admin.taxonomies.update', $job), ['name' => $job->name, 'is_active' => 0, 'skills' => []])->assertSessionHasNoErrors();
        $this->assertFalse($job->fresh()->is_active);
    }

    public function test_duplicate_names_mappings_and_invalid_skills_are_rejected(): void
    {
        $job = JobTaxonomy::factory()->create(['name' => 'Staf Gudang', 'normalized_name' => 'staf gudang']);
        $skill = Skill::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.taxonomies.store'), ['name' => 'STAF   GUDANG', 'is_active' => 1])->assertSessionHasErrors('normalized_name');
        $this->post(route('admin.taxonomies.store'), ['name' => 'Admin Inventori', 'is_active' => 1, 'skills' => [$skill->id, $skill->id]])->assertSessionHasErrors('skills.0');
        $skill->update(['is_active' => false]);
        $this->post(route('admin.taxonomies.store'), ['name' => 'Admin Inventori', 'is_active' => 1, 'skills' => [$skill->id]])->assertSessionHasErrors('skills.0');
        $this->assertDatabaseCount('job_taxonomies', 1);
    }

    public function test_applicant_cannot_manage_taxonomy_and_inactive_historical_links_are_preserved(): void
    {
        $job = JobTaxonomy::factory()->create();
        $skill = Skill::factory()->create(['is_active' => false]);
        $job->skills()->attach($skill);
        $this->actingAs(User::factory()->create())->post(route('admin.taxonomies.store'), ['name' => 'Dibajak'])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.taxonomies.update', $job), ['name' => $job->name, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('job_taxonomy_skill', ['job_taxonomy_id' => $job->id, 'skill_id' => $skill->id]);
    }
}
