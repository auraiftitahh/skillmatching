<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_detail_shows_cost_duration_provider_and_safe_external_source(): void
    {
        $training = Training::factory()->published()->create(['cost' => 125000, 'duration_hours' => 12, 'weekly_hours' => 3, 'source_url' => 'https://example.org/kursus']);
        $this->actingAs(User::factory()->create())->get(route('applicant.trainings.show', $training))->assertOk()->assertSee('Rp 125.000')->assertSee('12 jam')->assertSee('3 jam per pekan')->assertSee($training->body->name)->assertSee('target="_blank"', false)->assertSee('rel="noopener noreferrer"', false)->assertSee(route('applicant.skills.edit'));
        $this->assertDatabaseCount('training_enrollments', 0);
        $this->assertDatabaseCount('user_skills', 0);
    }

    public function test_prerequisite_labels_are_personal_and_unavailable_targets_have_no_broken_links(): void
    {
        [$target,$prior] = Training::factory()->published()->count(2)->create()->all();
        $user = User::factory()->create();
        $other = User::factory()->create();
        DB::table('training_prerequisites')->insert(['training_id' => $target->id, 'prerequisite_id' => $prior->id]);
        DB::table('training_enrollments')->insert(['user_id' => $other->id, 'training_id' => $prior->id, 'training_version' => 1, 'status' => 'completed']);
        $this->actingAs($user)->get(route('applicant.trainings.show', $target))->assertOk()->assertSee('Perlu diselesaikan lebih dahulu')->assertDontSee('Sudah selesai');
        DB::table('training_enrollments')->insert(['user_id' => $user->id, 'training_id' => $prior->id, 'training_version' => 1, 'status' => 'completed']);
        $this->get(route('applicant.trainings.show', $target))->assertSee('Sudah selesai');
        $prior->update(['status' => 'inactive']);
        $this->get(route('applicant.trainings.show', $target))->assertOk()->assertSee('sedang tidak tersedia')->assertDontSee(route('applicant.trainings.show', $prior), false);
    }

    public function test_back_link_preserves_valid_catalog_filters_and_empty_prerequisites_are_clear(): void
    {
        $training = Training::factory()->published()->create();
        $this->actingAs(User::factory()->create())->get(route('applicant.trainings.show', ['training' => $training->id, 'mode' => 'offline', 'max_cost' => 0]))->assertOk()->assertSee('Tidak ada prasyarat pelatihan')->assertSee('mode=offline')->assertSee('max_cost=0');
    }
}
