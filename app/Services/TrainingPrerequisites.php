<?php

namespace App\Services;

use App\Models\Training;
use App\Support\Publication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrainingPrerequisites
{
    public function save(Training $training, array $ids): void
    {
        $ids = array_map('intval', $ids);
        DB::transaction(function () use ($training, $ids): void {
            DB::table('catalog_locks')->where('name', 'training-prerequisites')->lockForUpdate()->first();
            $existing = DB::table('training_prerequisites')->where('training_id', $training->id)->pluck('prerequisite_id')->map(fn ($id) => (int) $id)->all();
            $eligible = Publication::trainings()->whereIn('trainings.id', $ids)->pluck('trainings.id')->map(fn ($id) => (int) $id)->all();
            if (in_array($training->id, $ids, true) || array_diff($ids, array_merge($existing, $eligible)) !== []) {
                throw ValidationException::withMessages(['prerequisites' => 'Pilih pelatihan yang layak dan jangan memilih pelatihan itu sendiri.']);
            }
            $edges = DB::table('training_prerequisites')->where('training_id', '!=', $training->id)->get()->groupBy('training_id')->map(fn ($rows) => $rows->pluck('prerequisite_id')->map(fn ($id) => (int) $id)->all())->all();
            $edges[$training->id] = $ids;
            $stack = $ids;
            $visited = [];
            while ($stack !== []) {
                $id = array_pop($stack);
                if ($id === $training->id) {
                    throw ValidationException::withMessages(['prerequisites' => 'Prasyarat membentuk siklus. Ubah pilihan agar urutan belajar dapat diikuti.']);
                }
                if (isset($visited[$id])) {
                    continue;
                }$visited[$id] = true;
                foreach ($edges[$id] ?? [] as $next) {
                    $stack[] = $next;
                }
            }
            DB::table('training_prerequisites')->where('training_id', $training->id)->delete();
            foreach ($ids as $id) {
                DB::table('training_prerequisites')->insert(['training_id' => $training->id, 'prerequisite_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
