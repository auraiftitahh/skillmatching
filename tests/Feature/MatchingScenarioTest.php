<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Skill;
use App\Models\TrainingSubmission;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\MatchingService;
use App\Services\TrainingReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function training(int $skill, int $level): int
    {
        $payload = TrainingSubmission::factory()->make()->payload;
        $payload['outcomes'] = [['skill_id' => $skill, 'target_level' => $level]];
        $s = TrainingSubmission::factory()->create(['payload' => $payload, 'status' => 'pending']);
        app(TrainingReview::class)->decide($s->id, User::factory()->admin()->create(), ['decision' => 'approved', 'review_note' => 'Capaian skenario telah diperiksa.']);

        return $s->fresh()->training_id;
    }

    private function fixture(): array
    {
        [$stock,$fork,$sheet,$safety] = Skill::factory()->count(4)->create()->all();
        $job = JobVacancy::factory()->create(['title' => 'Staf Gudang', 'status' => 'active']);
        foreach ([[$stock, 2, 'required', 3], [$fork, 2, 'required', 3], [$sheet, 2, 'required', 3], [$safety, 1, 'preferred', 1]] as [$skill,$target,$importance,$weight]) {
            $job->skills()->attach($skill, ['minimum_level' => $target, 'importance' => $importance, 'weight' => $weight, 'is_override' => false]);
        }
        $inventory = JobVacancy::factory()->create(['title' => 'Admin Inventori', 'status' => 'active']);
        $inventory->skills()->attach($sheet, ['minimum_level' => 2, 'importance' => 'required', 'weight' => 3, 'is_override' => false]);

        return [$job, $inventory, [$stock->id => 3, $sheet->id => 1, $safety->id => 2], $fork, $sheet];
    }

    public function test_reference_profiles_explain_55_to_85_and_50_to_50_for_same_forklift_training(): void
    {
        [$job,$inventory,$actual,$fork] = $this->fixture();
        $training = $this->training($fork->id, 2);
        $service = app(MatchingService::class);
        $projected = $service->projectedLevels($actual, [$training]);
        $this->assertSame(55.0, $service->score($job, $actual)['score']);
        $this->assertSame(85.0, $service->score($job, $projected)['score']);
        $this->assertSame(50.0, $service->score($inventory, $actual)['score']);
        $this->assertSame(50.0, $service->score($inventory, $projected)['score']);
    }

    public function test_empty_profile_explains_0_to_30_and_0_to_0_without_profile_mutation(): void
    {
        [$job,$inventory,$unused,$fork] = $this->fixture();
        $training = $this->training($fork->id, 2);
        $user = User::factory()->create();
        $service = app(MatchingService::class);
        $actual = $service->levels($user);
        $projected = $service->projectedLevels($actual, [$training]);
        $this->assertSame(0.0, $service->score($job, $actual)['score']);
        $this->assertSame(30.0, $service->score($job, $projected)['score']);
        $this->assertSame(0.0, $service->score($inventory, $projected)['score']);
        $this->assertDatabaseCount('user_skills', 0);
        $this->assertDatabaseCount('training_enrollments', 0);
        $this->assertDatabaseCount('skill_evidence', 0);
    }

    public function test_combined_training_takes_maximum_level_and_never_adds_outcomes_or_modifies_actual_skill(): void
    {
        [$job,$inventory,$actual,$fork,$sheet] = $this->fixture();
        $first = $this->training($fork->id, 2);
        $second = $this->training($fork->id, 3);
        $third = $this->training($sheet->id, 2);
        $service = app(MatchingService::class);
        $user = User::factory()->create();
        foreach ($actual as $skill => $level) {
            UserSkill::factory()->create(['user_id' => $user->id, 'skill_id' => $skill, 'self_reported_level' => $level, 'level' => $level]);
        }
        $before = $service->levels($user);
        $projected = $service->projectedLevels($before, [$first, $second, $third]);
        $this->assertSame(3, $projected[$fork->id]);
        $this->assertSame(100.0, $service->score($job, $projected)['score']);
        $this->assertSame($before,$service->levels($user));
        $this->assertDatabaseCount('training_enrollments',0);
    }
}
