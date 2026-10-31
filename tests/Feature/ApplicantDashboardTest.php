<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_has_actionable_empty_dashboard_without_fake_recommendations(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['phone' => null, 'domicile' => null, 'employment_status' => null]);
        $this->actingAs($user)->get('/pelamar/dashboard')->assertOk()->assertSee($user->name)->assertSee('Isi keterampilan saya')->assertSee('Nomor telepon')->assertSee('0 / 3');
    }

    public function test_dashboard_uses_only_own_skills_and_published_matches(): void
    {
        $this->withoutVite();
        $job = JobVacancy::factory()->published()->create();
        $user = User::factory()->create();
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $job->skills()->first()->id, 'level' => 2, 'self_reported_level' => 2]);
        $this->actingAs($user)->get('/pelamar/dashboard')->assertOk()->assertSee($job->title)->assertDontSee('Isi keterampilan saya');
        $this->actingAs(User::factory()->create())->get('/pelamar/dashboard')->assertOk()->assertDontSee($job->title);
    }
}
