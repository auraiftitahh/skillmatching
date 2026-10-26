<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\TrainingSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function approved(): TrainingSubmission
    {
        $s = TrainingSubmission::factory()->create(['status' => 'pending']);
        $p = $s->payload;
        $p['outcomes'][0]['skill_id'] = 'new';
        $s->update(['payload' => $p]);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.training-reviews.decide', $s), ['decision' => 'approved', 'review_note' => 'Isi awal sesuai.'])->assertSessionHasNoErrors();

        return $s->fresh();
    }

    public function test_revision_does_not_change_published_training_and_preserves_old_participant_outcomes(): void
    {
        $approved = $this->approved();
        $training = $approved->training;
        $skill = $training->skills()->first();
        $applicant = User::factory()->create();
        $enrollment = DB::table('training_enrollments')->insertGetId(['user_id' => $applicant->id, 'training_id' => $training->id, 'training_version' => 1, 'status' => 'planned', 'outcomes_snapshot' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($approved->body->user)->post(route('institution.trainings.revise', $training))->assertRedirect();
        $revision = TrainingSubmission::latest('id')->firstOrFail();
        $p = $revision->payload;
        $p['title'] = 'Forklift Lanjutan';
        $p['outcomes'][0]['target_level'] = 4;
        $this->put(route('institution.submissions.update', $revision), $p)->assertSessionHasNoErrors();
        $this->post(route('institution.submissions.send', $revision))->assertSessionHasNoErrors();
        $this->assertSame('Forklift Dasar', $training->fresh()->title);
        $this->assertSame(2, $training->skills()->first()->pivot->target_level);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.training-reviews.decide', $revision), ['decision' => 'approved', 'review_note' => 'Revisi sesuai.'])->assertSessionHasNoErrors();
        $this->assertSame('Forklift Lanjutan', $training->fresh()->title);
        $this->assertSame(2, $training->fresh()->version);
        $this->assertSame(4, $training->skills()->first()->pivot->target_level);
        $this->assertDatabaseCount('trainings', 1);
        $snapshot = json_decode(DB::table('training_enrollments')->find($enrollment)->outcomes_snapshot, true);
        $this->assertSame(2, $snapshot[0]['target_level']);
        $this->assertDatabaseHas('training_versions', ['training_id' => $training->id, 'version' => 1]);
        $this->assertDatabaseHas('training_versions', ['training_id' => $training->id, 'version' => 2]);
    }

    public function test_revision_request_and_resubmission_retain_review_history(): void
    {
        $s = TrainingSubmission::factory()->create(['status' => 'pending']);
        $p = $s->payload;
        $p['outcomes'][0]['skill_id'] = 'new';
        $s->update(['payload' => $p]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.training-reviews.decide', $s), ['decision' => 'revision', 'review_note' => 'Perjelas deskripsi operasional.'])->assertSessionHasNoErrors();
        $this->actingAs($s->body->user)->get(route('institution.submissions.edit', $s))->assertOk()->assertSee('Perjelas deskripsi operasional.');
        $p['description'] = 'Deskripsi diperbaiki sesuai catatan.';
        $this->put(route('institution.submissions.update', $s), $p)->assertSessionHasNoErrors();
        $this->post(route('institution.submissions.send', $s))->assertSessionHasNoErrors();
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertNull($s->fresh()->review_note);
        $this->assertDatabaseHas('training_submission_reviews', ['training_submission_id' => $s->id, 'decision' => 'revision']);
    }

    public function test_one_open_revision_per_training_and_other_owner_is_blocked(): void
    {
        $s = $this->approved();
        $this->actingAs($s->body->user)->post(route('institution.trainings.revise', $s->training))->assertRedirect();
        $this->post(route('institution.trainings.revise', $s->training))->assertRedirect();
        $this->assertDatabaseCount('training_submissions', 2);
        $other = CertificationBody::factory()->active()->create();
        $this->actingAs($other->user)->post(route('institution.trainings.revise', $s->training))->assertNotFound();
    }

    public function test_stale_revision_cannot_replace_a_newer_published_version(): void
    {
        $s = $this->approved();
        $this->actingAs($s->body->user)->post(route('institution.trainings.revise', $s->training));
        $revision = TrainingSubmission::latest('id')->firstOrFail();
        $this->post(route('institution.submissions.send', $revision))->assertSessionHasNoErrors();
        $s->training->update(['version' => 2]);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.training-reviews.decide', $revision), ['decision' => 'approved', 'review_note' => 'Versi sudah berubah.'])->assertStatus(409);
        $this->assertSame('pending',$revision->fresh()->status);
        $this->assertDatabaseCount('trainings',1);
    }
}
