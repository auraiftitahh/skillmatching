<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\User;
use App\Services\LearningPlans;
use App\Services\TrainingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_starts_and_submits_completion_without_receiving_skills_yet(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $course = Training::factory()->published()->create();
        $id = app(LearningPlans::class)->add($user, $course->id);
        $start = route('applicant.plans.start', $id);
        $submit = route('applicant.plans.submit-completion', $id);

        $this->actingAs(User::factory()->create())->post($start)->assertNotFound();
        $this->actingAs($user)->post($submit)->assertUnprocessable();
        $this->get(route('applicant.plans.index'))->assertOk()->assertSee('Mulai belajar');
        $this->post($start)->assertRedirect(route('applicant.plans.index'));
        $this->assertNotNull(DB::table('training_enrollments')->find($id)->started_at);
        $this->get(route('applicant.plans.index'))->assertOk()->assertSee('Ajukan penyelesaian');
        $this->post($submit)->assertRedirect(route('applicant.plans.index'));
        $submitted = DB::table('training_enrollments')->find($id);
        $this->assertSame('waiting_confirmation', $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->post($submit)->assertRedirect();
        $this->assertSame($submitted->submitted_at, DB::table('training_enrollments')->find($id)->submitted_at);
        $this->assertDatabaseCount('user_skills', 0);
    }

    public function test_plans_are_idempotent_capped_and_do_not_change_skills(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->actingAs($user);
        $courses = Training::factory()->count(4)->published()->create();
        foreach ($courses->take(3) as $course) {
            $this->post(route('applicant.plans.store'), ['training_id' => $course->id])->assertRedirect(route('applicant.plans.index'));
        }
        $this->post(route('applicant.plans.store'), ['training_id' => $courses[0]->id])->assertSessionHasNoErrors();
        $this->post(route('applicant.plans.store'), ['training_id' => $courses[3]->id])->assertSessionHasErrors('training_id');
        $this->assertDatabaseCount('training_enrollments', 3);
        $this->assertDatabaseCount('user_skills', 0);
        $this->get(route('applicant.plans.index'))->assertOk()->assertSee($courses[0]->title);
    }

    public function test_snapshot_survives_revision_and_only_owner_can_remove_unstarted_plan(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $course = Training::factory()->published()->create();
        $id = app(LearningPlans::class)->add($user, $course->id);
        $original = DB::table('training_enrollments')->find($id)->outcomes_snapshot;
        $course->skills()->updateExistingPivot($course->skills()->first()->id, ['target_level' => 4]);
        $course->update(['version' => 2]);
        TrainingSnapshot::preserve($course);
        $this->assertSame($original, DB::table('training_enrollments')->find($id)->outcomes_snapshot);
        $this->actingAs(User::factory()->create())->delete(route('applicant.plans.destroy', $id))->assertNotFound();
        DB::table('training_enrollments')->where('id', $id)->update(['status' => 'completed']);
        $this->actingAs($user)->delete(route('applicant.plans.destroy', $id))->assertUnprocessable();
        DB::table('training_enrollments')->where('id', $id)->update(['status' => 'planned']);
        $this->delete(route('applicant.plans.destroy', $id))->assertRedirect();
        $this->assertDatabaseCount('training_enrollments', 0);
    }

    public function test_hidden_course_and_unavailable_prerequisite_cannot_be_added(): void
    {
        $user = User::factory()->create();
        $course = Training::factory()->published()->create();
        $prereq = Training::factory()->create();
        DB::table('training_prerequisites')->insert(['training_id' => $course->id, 'prerequisite_id' => $prereq->id]);
        $this->actingAs($user)->post(route('applicant.plans.store'), ['training_id' => $course->id])->assertUnprocessable();
        $this->post(route('applicant.plans.store'), ['training_id' => $prereq->id])->assertNotFound();
        $this->assertDatabaseCount('training_enrollments', 0);
    }
}
