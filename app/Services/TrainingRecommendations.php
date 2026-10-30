<?php

namespace App\Services;

use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Support\Collection;

final class TrainingRecommendations
{
    public function __construct(private GapService $gaps) {}

    public function rank(User $user, JobVacancy $job, Collection $trainings): Collection
    {
        $gap = $this->gaps->forJob($user, $job);
        if ($gap['total_priority'] <= 0) {
            return collect();
        }

        return $trainings->map(function ($training) use ($gap) {
            $outcomes = $training->skills->keyBy('id');
            $gain = 0;
            $covered = [];
            foreach ($gap['items'] as $item) {
                $outcome = $outcomes->get($item['skill_id']);
                $improvement = $outcome ? max(0, min($item['target'], (int) $outcome->pivot->target_level) - $item['own']) : 0;
                if ($improvement > 0) {
                    $gain += $item['weight'] * $improvement;
                    $covered[] = $item['skill'];
                }
            }
            $training->setAttribute('gap_coverage', round(100 * $gain / $gap['total_priority'], 2));
            $training->setAttribute('covered_skills', $covered);

            return $training;
        })->filter(fn ($t) => $t->gap_coverage > 0)->sort(fn ($a, $b) => ($b->gap_coverage <=> $a->gap_coverage) ?: ((float) $a->cost <=> (float) $b->cost) ?: ($a->duration_hours <=> $b->duration_hours) ?: ($a->id <=> $b->id))->values();
    }
}
