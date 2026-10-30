<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\GapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GapCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gap_uses_current_applicant_level_and_orders_by_weighted_shortfall(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $required = $job->skills()->first();
        $preferred = Skill::factory()->create();
        $job->skills()->attach($preferred, ['minimum_level' => 2, 'importance' => 'preferred', 'weight' => 1, 'is_override' => false]);
        $user = User::factory()->create();
        $service = app(GapService::class);
        $gap = $service->forJob($user, $job);
        $this->assertCount(2, $gap['items']);
        $this->assertSame($required->id, $gap['items'][0]['skill_id']);
        $this->assertSame(6, $gap['items'][0]['priority']);
        $this->assertSame(8, $gap['total_priority']);
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $required->id, 'level' => 1, 'self_reported_level' => 1]);
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $preferred->id, 'level' => 2, 'self_reported_level' => 2]);
        $gap = $service->forJob($user, $job);
        $this->assertCount(1, $gap['items']);
        $this->assertSame(1, $gap['items'][0]['gap']);
        $this->assertSame(3, $gap['total_priority']);
    }

    public function test_related_skill_support_does_not_claim_direct_mastery_or_erase_direct_gap(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $required = $job->skills()->first();
        $related = Skill::factory()->create();
        $user = User::factory()->create();
        DB::table('skill_relations')->insert(['skill_id' => $required->id, 'related_skill_id' => $related->id, 'reason' => 'Kemampuan pendukung.']);
        UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $related->id, 'level' => 4, 'self_reported_level' => 4]);
        $gap = app(GapService::class)->forJob($user, $job);
        $this->assertTrue($gap['items'][0]['related']);
        $this->assertSame(0, $gap['items'][0]['own']);
        $this->assertSame(2, $gap['items'][0]['gap']);
    }

    public function test_complete_profile_has_no_gap_and_calculation_does_not_change_profile(): void
    {
        $job = JobVacancy::factory()->published()->create();
        $required = $job->skills()->first();
        $user = User::factory()->create();
        $record = UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $required->id, 'level' => 4, 'self_reported_level' => 4]);
        $before = $record->fresh()->getAttributes();
        $gap = app(GapService::class)->forJob($user, $job);
        $this->assertSame([], $gap['items']);
        $this->assertSame(0, $gap['total_priority']);
        $this->assertSame($before, $record->fresh()->getAttributes());
    }
}
