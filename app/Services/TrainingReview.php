<?php

namespace App\Services;

use App\Models\CertificationBody;
use App\Models\Skill;
use App\Models\Training;
use App\Models\TrainingSubmission;
use App\Models\User;
use App\Support\TrainingSubmissionPayload;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrainingReview
{
    public function decide(int $id, User $actor, array $data): void
    {
        abort_unless($actor->role === User::ROLE_ADMIN, 403);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                DB::transaction(function () use ($id, $actor, $data): void {
                    $initial = TrainingSubmission::findOrFail($id);
                    $body = CertificationBody::whereKey($initial->certification_body_id)->lockForUpdate()->firstOrFail();
                    $s = TrainingSubmission::whereKey($id)->lockForUpdate()->firstOrFail();
                    abort_unless($s->status === 'pending', 422, 'Pengajuan ini sudah ditinjau atau belum dikirim.');
                    $payload = $s->payload;
                    $trainingId = $s->training_id;
                    if ($data['decision'] === 'approved') {
                        abort_unless($body->status === 'active' && $body->user->is_active && $body->user->hasVerifiedEmail(), 422, 'Lembaga harus aktif dan emailnya terverifikasi.');
                        $payload = TrainingSubmissionPayload::validate($payload, true);
                        foreach (array_keys($data['mapping'] ?? []) as $index) {
                            if (! array_key_exists($index, $payload['outcomes'])) {
                                throw ValidationException::withMessages(['mapping' => 'Pemetaan tidak sesuai daftar capaian pengajuan.']);
                            }
                        }
                        $resolved = [];
                        foreach ($payload['outcomes'] as $index => &$outcome) {
                            $skillId = $outcome['skill_id'];
                            if ($skillId === 'new') {
                                $skillId = $data['mapping'][$index] ?? null;
                                if (! $skillId) {
                                    $registered = DB::table('skill_names')->where('normalized_name', Skill::normalize($outcome['new_name']))->first();
                                    $skillId = $registered?->skill_id;
                                    if (! $skillId) {
                                        $skillId = Skill::create(['name' => $outcome['new_name'], 'category' => $outcome['category'], 'description' => $outcome['description'], 'is_active' => true])->id;
                                    }
                                }
                            }
                            abort_unless(Skill::whereKey($skillId)->where('is_active', true)->exists(), 422, 'Padanan keterampilan harus aktif. Tinjau kembali pengajuan ini.');
                            $resolved[$skillId] = max($resolved[$skillId] ?? 0, (int) $outcome['target_level']);
                            $outcome['resolved_skill_id'] = (int) $skillId;
                        }
                        unset($outcome);
                        abort_if($trainingId !== null, 422, 'Revisi pelatihan belum dapat diproses melalui pengajuan baru.');
                        $training = Training::create([...array_intersect_key($payload, array_flip(['title', 'description', 'mode', 'duration_hours', 'weekly_hours', 'cost', 'source_url'])), 'certification_body_id' => $body->id, 'status' => 'active', 'version' => 1]);
                        foreach ($resolved as $skillId => $level) {
                            $training->skills()->attach($skillId, ['target_level' => $level]);
                        }
                        $trainingId = $training->id;
                    }
                    $s->update(['payload' => $payload, 'training_id' => $trainingId, 'status' => $data['decision'], 'review_note' => $data['review_note'], 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
                    DB::table('training_submission_reviews')->insert(['training_submission_id' => $s->id, 'user_id' => $actor->id, 'decision' => $data['decision'], 'note' => $data['review_note'], 'created_at' => now(), 'updated_at' => now()]);
                }, 3);

                return;
            } catch (UniqueConstraintViolationException $error) {
                if ($attempt === 2) {
                    throw ValidationException::withMessages(['mapping' => 'Terjadi perubahan data bersamaan. Muat ulang pengajuan lalu periksa padanan keterampilan.']);
                }
            }
        }
    }
}
