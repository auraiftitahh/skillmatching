<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApplicantSkills
{
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
