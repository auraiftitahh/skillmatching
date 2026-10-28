<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_applicant_can_search_details_and_save_without_applying_in_application(): void
    {
        $job = JobVacancy::factory()->published()->create(['title' => 'Staf Gudang']);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/pelamar/lowongan?q=Gudang')->assertOk()->assertSee($job->title);
        $this->get(route('applicant.jobs.show', $job))->assertOk()->assertSee($job->skills()->first()->name)->assertSee('Buka sumber lowongan')->assertSee('target="_blank"', false);
        $this->post(route('applicant.jobs.save', $job), ['user_id' => 999])->assertRedirect();
        $this->post(route('applicant.jobs.save', $job))->assertRedirect();
        $this->assertDatabaseCount('saved_jobs', 1);
        $this->assertDatabaseHas('saved_jobs', ['user_id' => $user->id, 'job_vacancy_id' => $job->id]);
        $this->get('/pelamar/lowongan?saved=1')->assertSee($job->title);
    }

    public function test_unpublished_jobs_are_blocked_on_list_detail_and_save(): void
    {
        $job = JobVacancy::factory()->published()->create(['status' => 'draft']);
        $this->actingAs(User::factory()->create());
        $this->get('/pelamar/lowongan')->assertDontSee($job->title);
        $this->get(route('applicant.jobs.show', $job))->assertNotFound();
        $this->post(route('applicant.jobs.save', $job))->assertNotFound();
        $this->assertDatabaseCount('saved_jobs', 0);
    }

    public function test_private_saved_jobs_are_isolated_and_expired_saved_jobs_can_be_removed(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        DB::table('saved_jobs')->insert(['user_id' => $owner->id, 'job_vacancy_id' => $job->id]);
        $this->actingAs($other)->get('/pelamar/lowongan?saved=1')->assertDontSee($job->title);
        $this->delete(route('applicant.jobs.unsave', $job))->assertNotFound();
        $job->update(['expires_at' => now('Asia/Jakarta')->subDay()->toDateString()]);
        $this->actingAs($owner)->get('/pelamar/lowongan?saved=1')->assertSee('Sudah tidak tersedia');
        $this->get(route('applicant.jobs.show', $job))->assertNotFound();
        $this->delete(route('applicant.jobs.unsave', $job))->assertRedirect();
        $this->assertDatabaseCount('saved_jobs', 0);
    }

    public function test_role_access_and_filter_validation_are_enforced(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/pelamar/lowongan')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/pelamar/lowongan?saved=bad')->assertSessionHasErrors('saved');
        $this->get('/pelamar/lowongan?employment_type=gig')->assertSessionHasErrors('employment_type');
    }
}
