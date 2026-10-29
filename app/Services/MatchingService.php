<?php

namespace App\Services;

use App\Models\JobVacancy;
use App\Models\User;
use App\Models\WeightConfiguration;
use App\Support\Publication;
use Illuminate\Support\Facades\DB;

final class MatchingService
{
    public function levels(User $user): array
    {
        return DB::table('user_skills as u')->join('skills as s', 's.id', '=', 'u.skill_id')->where('u.user_id', $user->id)->where('s.is_active', true)->where('u.level', '>', 0)->pluck('u.level', 'u.skill_id')->map(fn ($level) => (int) $level)->all();
    }

    public function score(JobVacancy $job, array $levels, ?WeightConfiguration $configuration = null): array
    {
        abort_unless(Publication::jobs()->whereKey($job->id)->exists(), 404, 'Posisi tujuan tidak tersedia untuk pencocokan.');
        $configuration ??= WeightConfiguration::current();
        $rows = $job->skills()->orderBy('skills.id')->get();
        $requirements = [];
        $related = [];
        $sources = [];
        $relations = DB::table('skill_relations as r')->join('skills as s', 's.id', '=', 'r.related_skill_id')->whereIn('r.skill_id', $rows->pluck('id'))->where('s.is_active', true)->orderBy('s.id')->get(['r.skill_id', 'r.related_skill_id', 's.name'])->groupBy('skill_id');
        foreach ($rows as $row) {
            $requirements[] = ['skill_id' => $row->id, 'skill' => $row->name, 'target' => (int) $row->pivot->minimum_level, 'importance' => $row->pivot->importance, 'weight' => (int) $row->pivot->weight, 'is_override' => (bool) $row->pivot->is_override];
            $related[$row->id] = [];
            foreach ($relations[$row->id] ?? [] as $relation) {
                $level = (int) ($levels[$relation->related_skill_id] ?? 0);
                if ($level <= 0) {
                    continue;
                }$related[$row->id][] = $level;
                if (! isset($sources[$row->id]) || $level > $sources[$row->id]['level']) {
                    $sources[$row->id] = ['id' => $relation->related_skill_id, 'name' => $relation->name, 'level' => $level];
                }
            }
        }
        $result = MatchingCalculator::calculate($requirements, $levels, $related, $configuration->only(['required_weight', 'preferred_weight', 'related_factor']));
        foreach ($result['items'] as &$item) {
            $item['related_skill_id'] = $item['related'] ? ($sources[$item['skill_id']]['id'] ?? null) : null;
            $item['related_skill'] = $item['related'] ? ($sources[$item['skill_id']]['name'] ?? null) : null;
            $item['source_level'] = $item['related'] ? ($sources[$item['skill_id']]['level'] ?? 0) : $item['own'];
        }
        unset($item);

        return [...$result, 'job_id' => $job->id, 'job' => $job->title, 'company' => $job->company, 'configuration_id' => $configuration->id, 'configuration_version' => $configuration->version, 'method' => 'max-direct-related-v2'];
    }

    public function rank(User $user, bool $includeBelow = false): array
    {
        $levels = $this->levels($user);
        if ($levels === []) {
            return [];
        }$configuration = WeightConfiguration::current();
        $results = [];
        foreach (Publication::jobs()->get() as $job) {
            $result = $this->score($job, $levels, $configuration);
            if ($includeBelow || $result['score'] >= $configuration->threshold) {
                $results[] = $result;
            }
        }
        usort($results, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['job'], $b['job']) ?: ($a['job_id'] <=> $b['job_id']));

        return $results;
    }
}
