<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\Skill;
use App\Models\Training;
use App\Models\User;
use App\Services\MatchingService;
use App\Services\TrainingSimulation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): void
    {
        config(['skillmatching.seed.owner_email' => 'admin@skillmatching.local', 'skillmatching.seed.owner_password' => 'Skillmatching7@', 'skillmatching.seed.demo_enabled' => true, 'skillmatching.seed.demo_password' => 'Skillmatching7@']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_reproduces_reference_scores_and_repeat_seed_preserves_customized_accounts(): void
    {
        $this->demo();
        $service = app(MatchingService::class);
        $raka = User::where('email', 'raka@example.com')->firstOrFail();
        $new = User::where('email', 'baru@example.test')->firstOrFail();
        $job = JobVacancy::where('title', 'Staf Gudang')->firstOrFail();
        $inventory = JobVacancy::where('title', 'Admin Inventori')->firstOrFail();
        $fork = Training::where('title', 'Forklift Dasar')->firstOrFail();
        $this->assertSame(55.0, $service->score($job, $service->levels($raka))['score']);
        $this->assertSame(85.0, app(TrainingSimulation::class)->run($raka, $job, [$fork->id])['after']['score']);
        $this->assertSame(50.0, app(TrainingSimulation::class)->run($raka, $inventory, [$fork->id])['after']['score']);
        $this->assertSame(30.0, app(TrainingSimulation::class)->run($new, $job, [$fork->id])['after']['score']);
        $this->assertSame(0.0, app(TrainingSimulation::class)->run($new, $inventory, [$fork->id])['after']['score']);
        $count = User::count();
        $raka->update(['password' => 'PersonalPassword123!', 'name' => 'Nama disesuaikan']);
        $hash = $raka->fresh()->password;
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($count, User::count());
        $this->assertSame($hash, $raka->fresh()->password);
        $this->assertSame('Nama disesuaikan', $raka->fresh()->name);
        $this->assertSame(Skill::count(), DB::table('skill_names')->where('is_primary', true)->count());
    }

    public function test_demo_is_disabled_by_default(): void
    {
        config(['skillmatching.seed.owner_email' => 'owner@example.test', 'skillmatching.seed.owner_password' => 'OwnerPassword123!', 'skillmatching.seed.demo_enabled' => false]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('skills', 0);
        $this->assertDatabaseCount('trainings', 0);
    }
}
