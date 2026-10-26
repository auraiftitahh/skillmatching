<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\TrainingSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function submission(): TrainingSubmission
    {
        $s = TrainingSubmission::factory()->create(['status' => 'pending']);
        $p = $s->payload;
        $p['outcomes'][0]['skill_id'] = 'new';
        $s->update(['payload' => $p, 'submitted_at' => now()]);

        return $s;
    }

    public function test_approval_publishes_training_and_skill_in_one_transaction(): void
    {
        $s = $this->submission();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->get('/admin/pengajuan-pelatihan')->assertOk()->assertSee('Forklift Dasar');
        $this->get(route('admin.training-reviews.show', $s))->assertOk()->assertSee('Padanan untuk Operasi Forklift');
        $this->post(route('admin.training-reviews.decide', $s), ['decision' => 'approved', 'review_note' => 'Isi dan capaian sesuai.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('skills', 1);
        $this->assertDatabaseCount('trainings', 1);
        $this->assertDatabaseCount('training_skills', 1);
        $this->assertSame('approved', $s->fresh()->status);
        $this->assertSame($admin->id, $s->fresh()->reviewed_by);
        $this->assertDatabaseHas('training_submission_reviews', ['training_submission_id' => $s->id, 'decision' => 'approved']);
        $this->post(route('admin.training-reviews.decide', $s), ['decision' => 'approved', 'review_note' => 'Tidak boleh berulang.'])->assertStatus(422);
        $this->assertDatabaseCount('trainings', 1);
        $this->actingAs($s->body->user)->get(route('institution.submissions.show', $s))->assertOk()->assertSee('Isi dan capaian sesuai.');
    }

    public function test_existing_name_alias_and_explicit_mapping_reuse_skill(): void
    {
        $skill = Skill::factory()->create(['name' => 'Operasi Forklift']);
        DB::table('skill_names')->insert(['skill_id' => $skill->id, 'name' => 'Operator forklift', 'normalized_name' => 'operator forklift', 'is_primary' => false]);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['  OPERASI   FORKLIFT ', 'Operator forklift', 'Kompetensi dengan nama berbeda'] as $i => $name) {
            $s = $this->submission();
            $p = $s->payload;
            $p['outcomes'][0]['new_name'] = $name;
            $s->update(['payload' => $p]);
            $data = ['decision' => 'approved', 'review_note' => 'Padanan kompetensi sudah sesuai.'];
            if ($i === 2) {
                $data['mapping'] = [0 => $skill->id];
            }
            $this->post(route('admin.training-reviews.decide', $s), $data)->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('skills', 1);
        $this->assertDatabaseCount('trainings', 3);
        $this->assertSame(3, DB::table('training_skills')->where('skill_id', $skill->id)->count());
    }

    public function test_revision_and_rejection_do_not_create_public_data_and_require_notes(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['revision', 'rejected'] as $decision) {
            $s = $this->submission();
            $this->post(route('admin.training-reviews.decide', $s), ['decision' => $decision])->assertSessionHasErrors('review_note');
            $this->post(route('admin.training-reviews.decide', $s), ['decision' => $decision, 'review_note' => 'Perjelas isi pelatihan dan cakupan.'])->assertSessionHasNoErrors();
            $this->assertSame($decision, $s->fresh()->status);
        }
        $this->assertDatabaseCount('trainings', 0);
        $this->assertDatabaseCount('skills', 0);
    }

    public function test_ineligible_body_and_invalid_mapping_cannot_publish_partial_data(): void
    {
        $s = $this->submission();
        $s->body->update(['status' => 'disabled']);
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.training-reviews.decide', $s), ['decision' => 'approved', 'review_note' => 'Akun sudah tidak layak.'])->assertStatus(422);
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertDatabaseCount('skills', 0);
        $s->body->update(['status' => 'active']);
        $inactive = Skill::factory()->create(['is_active' => false]);
        $this->post(route('admin.training-reviews.decide', $s), ['decision' => 'approved', 'review_note' => 'Pemetaan tidak valid.', 'mapping' => [0 => $inactive->id]])->assertSessionHasErrors('mapping.0');
        $this->assertDatabaseCount('trainings', 0);
    }

    public function test_only_admin_can_decide_and_draft_cannot_be_reviewed(): void
    {
        $s = $this->submission();
        $this->actingAs($s->body->user)->post(route('admin.training-reviews.decide', $s), ['decision' => 'approved', 'review_note' => 'Tidak berwenang.'])->assertForbidden();
        $s->update(['status' => 'draft']);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.training-reviews.decide', $s), ['decision' => 'approved', 'review_note' => 'Belum dikirim untuk tinjauan.'])->assertStatus(422);
        $this->assertDatabaseCount('skills',0);
    }
}
