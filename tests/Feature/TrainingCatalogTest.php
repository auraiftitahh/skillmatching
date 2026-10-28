<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_applicant_sees_approved_training_and_can_open_matching_detail(): void
    {
        $training = Training::factory()->published()->create(['title' => 'Pelatihan Forklift Khusus']);
        $hidden = Training::factory()->create(['title' => 'Pelatihan Belum Disetujui']);
        $this->actingAs(User::factory()->create())->get('/pelamar/pelatihan')->assertOk()->assertSee($training->title)->assertDontSee($hidden->title);
        $this->get(route('applicant.trainings.show', $training))->assertOk()->assertSee($training->description)->assertSee($training->skills()->first()->name);
        $this->assertDatabaseCount('user_skills', 0);
        $this->assertDatabaseCount('training_enrollments', 0);
    }

    public function test_publication_rules_also_apply_to_direct_urls(): void
    {
        $training = Training::factory()->published()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $training->body->update(['status' => 'disabled']);
        $this->get(route('applicant.trainings.show', $training))->assertNotFound();
        $training->body->update(['status' => 'active']);
        $training->update(['status' => 'inactive']);
        $this->get(route('applicant.trainings.show', $training))->assertNotFound();
        $this->get('/pelamar/pelatihan/99999')->assertNotFound();
    }

    public function test_filters_are_consistent_and_invalid_input_is_rejected(): void
    {
        $online = Training::factory()->published()->create(['title' => 'Spreadsheet Dasar', 'mode' => 'online', 'cost' => 0]);
        $offline = Training::factory()->published()->create(['title' => 'Forklift Lapangan', 'mode' => 'offline', 'cost' => 500000]);
        $this->actingAs(User::factory()->create())->get('/pelamar/pelatihan?mode=online&max_cost=0')->assertSee($online->title)->assertDontSee($offline->title);
        $this->get('/pelamar/pelatihan?q=Forklift')->assertSee($offline->title)->assertDontSee($online->title);
        $this->get('/pelamar/pelatihan?mode=bad')->assertSessionHasErrors('mode');
        $this->get('/pelamar/pelatihan?max_cost=-1')->assertSessionHasErrors('max_cost');
    }

    public function test_empty_catalog_is_actionable_and_other_roles_cannot_use_applicant_routes(): void
    {
        $this->actingAs(User::factory()->create())->get('/pelamar/pelatihan')->assertOk()->assertSee('Belum ada pelatihan yang sesuai');
        $this->actingAs(User::factory()->admin()->create())->get('/pelamar/pelatihan')->assertForbidden();
    }
}
