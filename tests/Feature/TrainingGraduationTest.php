<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\Training;
use App\Models\User;
use App\Services\ApplicantSkills;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingGraduationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_own_institution_can_confirm_and_record_original_outcomes_once(): void
    {
        $this->withoutVite();
        $applicant = User::factory()->create();
        $training = Training::factory()->published()->create();
        $skillId = $training->skills()->firstOrFail()->id;
        $owner = $training->body->user;
        $other = CertificationBody::factory()->active()->create()->user;

        $this->actingAs($applicant)->post(route('applicant.plans.store'), ['training_id' => $training->id])->assertRedirect();
        $id = DB::table('training_enrollments')->where('user_id', $applicant->id)->value('id');
        $confirm = route('institution.participants.confirm', $id);
        $this->post(route('applicant.plans.start', $id))->assertRedirect();
        $this->actingAs($owner)->post($confirm)->assertUnprocessable();
        $this->actingAs($applicant)->post(route('applicant.plans.submit-completion', $id))->assertRedirect();
        $this->assertDatabaseCount('skill_evidence', 0);

        $training->skills()->updateExistingPivot($skillId, ['target_level' => 4]);
        $training->update(['version' => 2]);
        app(ApplicantSkills::class)->saveSelfReported($applicant, [$skillId => 3]);

        $this->actingAs($other)->get(route('institution.participants.index'))->assertOk()->assertDontSee($applicant->name);
        $this->post($confirm)->assertNotFound();
        $this->actingAs($owner)->get(route('institution.participants.index'))->assertOk()->assertSee($applicant->name);
        $this->post($confirm)->assertRedirect(route('institution.participants.index'));
        $this->post($confirm)->assertRedirect();

        $this->assertDatabaseHas('training_enrollments', ['id' => $id, 'status' => 'completed', 'training_version' => 1, 'confirmed_by' => $owner->id]);
        $this->assertDatabaseCount('skill_evidence', 1);
        $this->assertDatabaseHas('skill_evidence', ['user_id' => $applicant->id, 'skill_id' => $skillId, 'level' => 2, 'source_type' => 'catalog_graduation', 'source_id' => $id]);
        $this->assertDatabaseHas('user_skills', ['user_id' => $applicant->id, 'skill_id' => $skillId, 'self_reported_level' => 3, 'level' => 3, 'verification_status' => 'self_reported']);
    }
}
