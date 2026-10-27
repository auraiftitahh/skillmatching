<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplicantSkillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_selection_is_saved_only_on_submit_for_current_applicant(): void
    {
        $skill = Skill::factory()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $this->get('/pelamar/skill')->assertOk()->assertSee($skill->name);
        $this->assertDatabaseCount('user_skills', 0);
        $this->put('/pelamar/skill', ['levels' => [$skill->id => 3], 'user_id' => $other->id, 'verification_status' => 'verified'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_skills', ['user_id' => $user->id, 'skill_id' => $skill->id, 'level' => 3, 'verification_status' => 'self_reported']);
        $this->assertDatabaseMissing('user_skills', ['user_id' => $other->id]);
        $this->put('/pelamar/skill', ['levels' => [$skill->id => 0]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('user_skills', 0);
    }

    public function test_lower_proof_does_not_verify_higher_self_claim_and_is_not_removed_by_deselection(): void
    {
        $skill = Skill::factory()->create();
        $user = User::factory()->create();
        $issuer = User::factory()->institution()->create();
        DB::table('skill_evidence')->insert(['user_id' => $user->id, 'skill_id' => $skill->id, 'level' => 2, 'source_type' => 'training', 'source_id' => 1, 'confirmed_by' => $issuer->id]);
        $this->actingAs($user)->put('/pelamar/skill', ['levels' => [$skill->id => 4]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_skills', ['user_id' => $user->id, 'level' => 4, 'self_reported_level' => 4, 'verification_status' => 'self_reported']);
        $this->put('/pelamar/skill', ['levels' => [$skill->id => 0]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_skills', ['user_id' => $user->id, 'level' => 2, 'self_reported_level' => 0, 'verification_status' => 'verified']);
        $this->assertDatabaseCount('skill_evidence', 1);
    }

    public function test_inactive_unknown_or_invalid_levels_do_not_partially_update_profile(): void
    {
        [$active,$inactive] = Skill::factory()->count(2)->create()->all();
        $inactive->update(['is_active' => false]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->put('/pelamar/skill', ['levels' => [$active->id => 3, $inactive->id => 2]])->assertSessionHasErrors('levels');
        $this->assertDatabaseCount('user_skills', 0);
        $this->put('/pelamar/skill', ['levels' => [999 => 2]])->assertSessionHasErrors('levels');
        $this->put('/pelamar/skill', ['levels' => [$active->id => 5]])->assertSessionHasErrors('levels.'.$active->id);
        $this->put('/pelamar/skill', ['levels' => ['bad' => 2]])->assertSessionHasErrors('levels');
    }

    public function test_omitted_choices_and_inactive_historical_records_are_preserved(): void
    {
        $user = User::factory()->create();
        $own = UserSkill::factory()->create(['user_id' => $user->id]);
        $another = Skill::factory()->create();
        $own->skill->update(['is_active' => false]);
        $this->actingAs($user)->get('/pelamar/skill')->assertOk()->assertSee($own->skill->name);
        $this->put('/pelamar/skill', ['levels' => [$another->id => 1]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_skills', ['id' => $own->id, 'level' => 2]);
    }

    public function test_other_roles_cannot_edit_and_empty_catalog_has_clear_next_step(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put('/pelamar/skill', ['levels' => [1 => 4]])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/pelamar/skill')->assertOk()->assertSee('Belum ada keterampilan tersedia')->assertSee(route('applicant.experiences.index'));
    }
}
