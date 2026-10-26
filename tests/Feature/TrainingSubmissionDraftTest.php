<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\Skill;
use App\Models\TrainingSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSubmissionDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_active_institution_can_save_partial_draft_without_publishing_or_creating_skills(): void
    {
        $body = CertificationBody::factory()->active()->create();
        $skill = Skill::factory()->create();
        $this->actingAs($body->user)->get('/sertifikasi/pengajuan')->assertOk();
        $this->get(route('institution.submissions.create'))->assertOk()->assertSee($skill->name);
        $this->post(route('institution.submissions.store'), ['title' => 'Draf saya', 'status' => 'approved', 'certification_body_id' => 999])->assertSessionHasNoErrors();
        $record = TrainingSubmission::firstOrFail();
        $this->assertSame($body->id, $record->certification_body_id);
        $this->assertSame('draft', $record->status);
        $this->assertDatabaseCount('trainings', 0);
        $this->assertDatabaseCount('skills', 1);
        $this->get(route('institution.submissions.show', $record))->assertOk()->assertSee('Draf saya');
        $this->put(route('institution.submissions.update', $record), ['title' => 'Draf diperbaiki'])->assertSessionHasNoErrors();
        $this->assertSame('Draf diperbaiki', $record->fresh()->payload['title']);
    }

    public function test_other_body_and_other_roles_cannot_read_or_edit_draft(): void
    {
        $record = TrainingSubmission::factory()->create();
        $other = CertificationBody::factory()->active()->create();
        $this->actingAs($other->user)->get(route('institution.submissions.show', $record))->assertNotFound();
        $this->put(route('institution.submissions.update', $record), ['title' => 'Diubah pihak lain'])->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/sertifikasi/pengajuan')->assertForbidden();
        $this->assertSame('Forklift Dasar', $record->fresh()->payload['title']);
    }

    public function test_pending_institution_is_blocked_and_invalid_values_are_rejected(): void
    {
        $body = CertificationBody::factory()->create();
        $this->actingAs($body->user)->get('/sertifikasi/pengajuan')->assertRedirect(route('institution.status'));
        $body->update(['status' => 'active']);
        $this->actingAs($body->user->fresh())->post(route('institution.submissions.store'), ['title' => '', 'cost' => -1, 'source_url' => 'javascript:alert(1)'])->assertSessionHasErrors(['title', 'cost', 'source_url']);
        $this->assertDatabaseCount('training_submissions', 0);
    }

    public function test_skill_suggestions_are_stored_in_draft_only(): void
    {
        $body = CertificationBody::factory()->active()->create();
        $payload = TrainingSubmission::factory()->make()->payload;
        $payload['outcomes'][0]['skill_id'] = 'new';
        $this->actingAs($body->user)->post(route('institution.submissions.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('skills', 0);
        $this->assertDatabaseCount('trainings', 0);
        $this->assertDatabaseCount('training_submissions',1);
    }
}
