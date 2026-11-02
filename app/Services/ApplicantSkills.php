<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApplicantSkills
{
    public function recordEvidence(int $userId, int $skillId, int $level, string $sourceType, int $sourceId, int $confirmedBy): void
    {
        DB::transaction(function () use ($userId, $skillId, $level, $sourceType, $sourceId, $confirmedBy): void {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            DB::table('skill_evidence')->insertOrIgnore([
                'user_id' => $userId,
                'skill_id' => $skillId,
                'level' => $level,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'confirmed_by' => $confirmedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $skill = UserSkill::firstOrNew(['user_id' => $userId, 'skill_id' => $skillId]);
            $self = (int) $skill->self_reported_level;
            $supported = (int) DB::table('skill_evidence')->where('user_id', $userId)->where('skill_id', $skillId)->max('level');
            $skill->fill([
                'self_reported_level' => $self,
                'level' => max($self, $supported),
                'verification_status' => $supported >= $self ? 'verified' : 'self_reported',
            ])->save();
        }, 3);
    }

    public function saveSelfReported(User $user, array $levels): void
    {
        $ids = [];
        foreach ($levels as $id => $level) {
            if (! ctype_digit((string) $id) || (string) (int) $id !== (string) $id || (int) $id < 1) {
                throw ValidationException::withMessages(['levels' => 'Pilihan keterampilan tidak valid.']);
            }
            $ids[] = (int) $id;
        }
        DB::transaction(function () use ($user, $levels, $ids): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $skills = Skill::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($skills->count() !== count($ids) || $skills->contains(fn ($s) => ! $s->is_active)) {
                throw ValidationException::withMessages(['levels' => 'Ada keterampilan yang tidak lagi tersedia. Muat ulang pilihan Anda.']);
            }
            foreach ($levels as $id => $level) {
                $self = (int) $level;
                $supported = (int) DB::table('skill_evidence')->where('user_id', $user->id)->where('skill_id', $id)->max('level');
                $effective = max($self, $supported);
                if ($effective === 0) {
                    UserSkill::where('user_id', $user->id)->where('skill_id', $id)->delete();

                    continue;
                }
                UserSkill::updateOrCreate(['user_id' => $user->id, 'skill_id' => $id], ['self_reported_level' => $self, 'level' => $effective, 'verification_status' => $supported >= $effective ? 'verified' : 'self_reported']);
            }
        });
    }
}
