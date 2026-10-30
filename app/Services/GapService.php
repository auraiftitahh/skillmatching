<?php

namespace App\Services;

use App\Models\JobVacancy;
use App\Models\User;

final class GapService
{
    public function __construct(private MatchingService $matching) {}

    public function forJob(User $user, JobVacancy $job): array
    {
        $score = $this->matching->score($job, $this->matching->levels($user));
        $items = array_values(array_filter($score['items'], fn ($item) => $item['gap'] > 0));
        usort($items, fn ($a, $b) => ($b['priority'] <=> $a['priority']) ?: ($b['gap'] <=> $a['gap']) ?: strcmp($a['skill'], $b['skill']));

        return ['job_id' => $job->id, 'job' => $job->title, 'company' => $job->company, 'items' => $items, 'total_priority' => array_sum(array_column($items, 'priority')), 'configuration_id' => $score['configuration_id'], 'configuration_version' => $score['configuration_version']];
    }
}
