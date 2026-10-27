<?php

namespace App\Support;

use App\Models\Training;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class Publication
{
    public static function trainings(bool $activeOnly = true): Builder
    {
        $query = Training::query()->whereHas('body', fn ($q) => $q->where('status', 'active')->whereHas('user', fn ($u) => $u->where('is_active', true)->whereNotNull('email_verified_at')))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('training_submissions')->whereColumn('training_submissions.training_id', 'trainings.id')->where('status', 'approved'))
            ->whereHas('skills')->whereDoesntHave('skills', fn ($q) => $q->where('skills.is_active', false));
        if ($activeOnly) {
            $query->where('trainings.status', 'active');
        }

        return $query;
    }

    public static function prerequisitesMet(int $userId, int $trainingId): bool
    {
        $required = DB::table('training_prerequisites')->where('training_id', $trainingId)->pluck('prerequisite_id')->map(fn ($id) => (int) $id)->all();
        $completed = DB::table('training_enrollments')->where('user_id', $userId)->where('status', 'completed')->pluck('training_id')->map(fn ($id) => (int) $id)->all();

        return array_diff($required, $completed) === [];
    }
}
