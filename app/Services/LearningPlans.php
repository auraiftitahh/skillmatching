<?php

namespace App\Services;

use App\Models\User;
use App\Support\Publication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LearningPlans
{
    public const ACTIVE = ['planned', 'in_progress', 'waiting_confirmation'];

    public function add(User $user, int $trainingId): int
    {
        return DB::transaction(function () use ($user, $trainingId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('training_enrollments')->where('user_id', $user->id)->where('training_id', $trainingId)->first();
            if ($existing) {
                return (int) $existing->id;
            }
            $training = Publication::trainings()->lockForUpdate()->findOrFail($trainingId);
            if (DB::table('training_enrollments')->where('user_id', $user->id)->whereIn('status', self::ACTIVE)->count() >= 3) {
                throw ValidationException::withMessages(['training_id' => 'Maksimal tiga rencana aktif. Hapus rencana yang belum dimulai sebelum menambah pelatihan lain.']);
            }
            abort_if(app(TrainingPath::class)->forTraining($user, $training)['blocked'], 422, 'Prasyarat pelatihan tidak tersedia. Pilih jalur lain terlebih dahulu.');
            TrainingSnapshot::preserve($training);

            return DB::table('training_enrollments')->insertGetId(['user_id' => $user->id, 'training_id' => $training->id, 'training_version' => $training->version, 'outcomes_snapshot' => json_encode(TrainingSnapshot::outcomes($training)), 'status' => 'planned', 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function remove(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $record = DB::table('training_enrollments')->where('user_id', $user->id)->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->status === 'planned', 422, 'Hanya rencana yang belum dimulai yang dapat dihapus.');
            DB::table('training_enrollments')->where('id', $record->id)->delete();
        });
    }
}
