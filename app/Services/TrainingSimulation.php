<?php

namespace App\Services;

use App\Models\JobVacancy;
use App\Models\User;
use App\Models\WeightConfiguration;

final class TrainingSimulation
{
    public function __construct(private MatchingService $matching) {}

    public function run(User $user, JobVacancy $job, array $trainingIds): array
    {
        $configuration = WeightConfiguration::current();
        $actual = $this->matching->levels($user);
        $projected = $this->matching->projectedLevels($actual, $trainingIds);
        $before = $this->matching->score($job, $actual, $configuration);
        $after = $this->matching->score($job, $projected, $configuration);

        return ['before' => $before, 'after' => $after, 'delta' => round($after['score'] - $before['score'], 2), 'training_ids' => $trainingIds, 'actual' => $actual, 'projected' => $projected];
    }
}
