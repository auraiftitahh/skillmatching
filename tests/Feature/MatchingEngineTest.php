<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Models\WeightConfiguration;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_best_related_contribution_survives_addition_of_weaker_direct_skill(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $required = $job->skills()->first();
        $job->skills()->updateExistingPivot($required->id, ['minimum_level' => 4]);
        $related = Skill::factory()->create();
        DB::table('skill_relations')->insert(['skill_id' => $required->id, 'related_skill_id' => $related->id, 'reason' => 'Kemampuan yang mendukung.']);
        $service = app(MatchingService::class);
        $this->assertSame(50.0, $service->score($job, [$related->id => 4])['score']);
        $result = $service->score($job, [$related->id => 4, $required->id => 1]);
        $this->assertSame(50.0, $result['score']);
        $this->assertSame($related->name, $result['items'][0]['related_skill']);
        $this->assertSame(75.0, $service->score($job, [$related->id => 4, $required->id => 3])['score']);
        $this->assertDatabaseCount('user_skills', 0);
    }

    public function test_calculation_can_reuse_an_immutable_old_weight_version(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $own = $job->skills()->first();
        $other = Skill::factory()->create();
        $job->skills()->attach($other, ['minimum_level' => 2, 'importance' => 'preferred', 'weight' => 1, 'is_override' => false]);
        $old = WeightConfiguration::current();
        $service = app(MatchingService::class);
        $this->assertSame(75.0, $service->score($job, [$own->id => 2], $old)['score']);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.weights.store'), ['base_version' => 1, 'required_weight' => 5, 'preferred_weight' => 1, 'related_factor' => 0.5, 'threshold' => 40, 'reason' => 'Perubahan bobot untuk pengujian versi.'])->assertSessionHasNoErrors();
        $this->assertSame(83.33, $service->score($job, [$own->id => 2])['score']);
        $this->assertSame(75.0, $service->score($job, [$own->id => 2], $old)['score']);
        $job->skills()->updateExistingPivot($own->id, ['weight' => 1, 'is_override' => true]);
        $this->assertSame(50.0, $service->score($job, [$own->id => 2])['score']);
    }

    public function test_empty_or_unrelated_profile_has_no_primary_recommendations_and_inactive_profile_skills_are_ignored(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $user = User::factory()->create();
        $service = app(MatchingService::class);
        $this->assertSame([], $service->rank($user));
        $skill = Skill::factory()->create();
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $skill->id]);
        $this->assertSame([], $service->rank($user));
        $this->assertCount(1, $service->rank($user, true));
        $skill->update(['is_active' => false]);
        $this->assertSame([], $service->levels($user));
        $this->assertSame([],$service->rank($user,true));
    }
}
