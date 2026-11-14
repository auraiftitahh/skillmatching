<?php
namespace App\Support;

use App\Models\{JobVacancy, Training};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Shared publication rules for lists, direct links, recommendations and actions. */
final class Publication
{
    public static function jobs(): Builder
    {
        return JobVacancy::where('status', 'active')
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->whereExists(fn($q) => $q->selectRaw('1')->from('job_required_skills')
                ->whereColumn('job_vacancy_id', 'job_vacancies.id'));
    }

    public static function trainings(): Builder
    {
        return Training::where('status', 'active')->whereExists(fn($q) => $q->selectRaw('1')
            ->from('training_skills')->whereColumn('training_id', 'trainings.id'))
            ->where(function ($q) {
                $q->whereNull('certification_body_id')->orWhereIn('certification_body_id', self::bodyIds());
            });
    }

    public static function bodyIds()
    {
        return DB::table('certification_bodies as cb')->join('users as bu', 'bu.id', '=', 'cb.user_id')
            ->where('cb.status', 'active')->where('bu.is_active', true)->whereNotNull('bu.email_verified_at')
            ->select('cb.id');
    }

    public static function prerequisitesMet(int $userId,int $trainingId): bool
    {
        $required=DB::table('training_prerequisites')->where('training_id',$trainingId)->pluck('prerequisite_id')->map(fn($x)=>(int)$x)->all();
        $done=DB::table('training_enrollments')->where('user_id',$userId)->where('status','completed')->pluck('training_id')->map(fn($x)=>(int)$x)->all();
        return count(array_diff($required,$done))===0;
    }
}
