<?php

namespace App\Services;

use App\Models\Training;
use Illuminate\Support\Facades\DB;

final class TrainingSnapshot
{
    public static function outcomes(Training $training): array
    {
        return $training->skills()->get()->map(fn ($s) => ['skill_id' => $s->id, 'target_level' => (int) $s->pivot->target_level])->all();
    }

    public static function preserve(Training $training): void
    {
        $outcomes = self::outcomes($training);
        DB::table('training_versions')->insertOrIgnore(['training_id' => $training->id, 'version' => $training->version, 'snapshot' => json_encode([...$training->getAttributes(), 'outcomes' => $outcomes]), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('training_enrollments')->where('training_id', $training->id)->whereNull('outcomes_snapshot')->update(['outcomes_snapshot' => json_encode($outcomes), 'training_version' => $training->version]);
    }
}
