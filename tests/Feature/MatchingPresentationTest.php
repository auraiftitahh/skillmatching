<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_matching_views_explain_real_profile_without_exposing_formula_implementation(): void
    {
        $job = JobVacancy::factory()->published()->create(['title' => 'Staf Gudang']);
        $skill = $job->skills()->first();
        $user = User::factory()->create();
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $skill->id, 'level' => 2, 'self_reported_level' => 2]);
        $this->actingAs($user)->get(route('applicant.matching.index'))->assertOk()->assertSee('Staf Gudang')->assertSee('100')->assertSee('bukan peluang diterima kerja');
        $this->get(route('applicant.matching.show', $job))->assertOk()->assertSee('Level sudah memenuhi kebutuhan')->assertDontSee('max-direct-related-v2')->assertDontSee('Sumber rumus');
    }

    public function test_empty_profile_has_guidance_but_can_inspect_zero_score_for_chosen_target(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $this->actingAs(User::factory()->create())->get(route('applicant.matching.index'))->assertOk()->assertSee('Lengkapi kemampuan');
        $this->get(route('applicant.matching.show', $job))->assertOk()->assertSee('Belum tercatat di profil Anda');
        $this->assertDatabaseCount('user_skills', 0);
    }

    public function test_inactive_targets_and_other_roles_are_blocked(): void
    {
        $job = JobVacancy::factory()->published()->create(['status' => 'inactive']);
        $this->actingAs(User::factory()->create())->get(route('applicant.matching.show', $job))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get(route('applicant.matching.index'))->assertForbidden();
    }

    public function test_filter_validation_and_out_of_range_pagination_are_safe(): void
    {
        $this->actingAs(User::factory()->create())->get(route('applicant.matching.index', ['all' => 'bad']))->assertSessionHasErrors('all');
        $this->get(route('applicant.matching.index', ['page' => 500]))->assertRedirect(route('applicant.matching.index', ['page' => 1]));
    }
}
