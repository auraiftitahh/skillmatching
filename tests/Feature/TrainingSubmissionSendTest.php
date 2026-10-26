<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\Skill;
use App\Models\TrainingSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSubmissionSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function draft(): TrainingSubmission
    {
        $s = TrainingSubmission::factory()->create();
        $p = $s->payload;
        $p['outcomes'][0]['skill_id'] = 'new';
        $s->update(['payload' => $p]);

        return $s;
    }

    public function test_sending_complete_draft_only_marks_it_pending_without_publishing(): void
    {
        $s = $this->draft();
        $this->actingAs($s->body->user)->post(route('institution.submissions.send', $s))->assertSessionHasNoErrors()->assertRedirect(route('institution.submissions.show', $s));
        $this->assertSame('pending', $s->fresh()->status);
        $this->assertNotNull($s->fresh()->submitted_at);
        $this->assertDatabaseCount('skills', 0);
        $this->assertDatabaseCount('trainings', 0);
        $this->get(route('institution.submissions.show', $s))->assertOk()->assertSee('Menunggu tinjauan')->assertDontSee('Lanjutkan mengisi draf');
        $this->post(route('institution.submissions.send', $s))->assertStatus(422);
        $this->put(route('institution.submissions.update', $s), ['title' => 'Tidak boleh'])->assertStatus(422);
    }

    public function test_incomplete_draft_remains_editable_and_gives_actionable_errors(): void
    {
        $s = $this->draft();
        $s->update(['payload' => ['title' => 'Belum lengkap']]);
        $this->actingAs($s->body->user)->post(route('institution.submissions.send', $s))->assertSessionHasErrors(['description', 'source_url', 'outcomes']);
        $this->assertSame('draft', $s->fresh()->status);
    }

    public function test_new_skill_needs_meaningful_description_and_duplicates_are_rejected(): void
    {
        $s = $this->draft();
        $p = $s->payload;
        $p['outcomes'][0]['description'] = '';
        $p['outcomes'][] = $p['outcomes'][0];
        $s->update(['payload' => $p]);
        $this->actingAs($s->body->user)->post(route('institution.submissions.send', $s))->assertSessionHasErrors(['outcomes', 'outcomes.0.description']);
        $this->assertSame('draft', $s->fresh()->status);
    }

    public function test_inactive_skill_cannot_be_submitted_and_other_body_cannot_send(): void
    {
        $s = $this->draft();
        $other = CertificationBody::factory()->active()->create();
        $this->actingAs($other->user)->post(route('institution.submissions.send', $s))->assertNotFound();
        $skill = Skill::factory()->create(['is_active' => false]);
        $p = $s->payload;
        $p['outcomes'] = [['skill_id' => $skill->id, 'target_level' => 2]];
        $s->update(['payload' => $p]);
        $this->actingAs($s->body->user)->post(route('institution.submissions.send', $s))->assertSessionHasErrors('outcomes.0.skill_id');
        $this->assertSame('draft', $s->fresh()->status);
    }

    public function test_existing_skill_cannot_carry_hidden_new_skill_fields(): void
    {
        $s = $this->draft();
        $skill = Skill::factory()->create();
        $p = $s->payload;
        $p['outcomes'] = [['skill_id' => $skill->id, 'target_level' => 2, 'new_name' => 'Tidak boleh dibuat', 'category' => 'Palsu']];
        $this->actingAs($s->body->user)->put(route('institution.submissions.update', $s), $p)->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('new_name', $s->fresh()->payload['outcomes'][0]);
        $this->post(route('institution.submissions.send', $s))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('skills',1);
    }
}
