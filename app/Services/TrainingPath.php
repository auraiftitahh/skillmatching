<?php

namespace App\Services;

use App\Models\Training;
use App\Models\User;
use App\Support\Publication;
use Illuminate\Support\Facades\DB;

final class TrainingPath
{
    public function forTraining(User $user, Training $training): array
    {
        $completed = DB::table('training_enrollments')->where('user_id', $user->id)->where('status', 'completed')->pluck('training_id')->all();
        $edges = DB::table('training_prerequisites')->orderBy('prerequisite_id')->get()->groupBy('training_id');
        $available = Publication::trainings()->get()->keyBy('id');
        $all = Training::all()->keyBy('id');
        $visited = [];
        $visiting = [];
        $steps = [];
        $blocked = false;
        $visit = function (int $id) use (&$visit, &$visited, &$visiting, &$steps, &$blocked, $edges, $available, $all, $completed): void {
            if (isset($visited[$id]) || in_array($id, $completed)) {
                return;
            }
            if (isset($visiting[$id])) {
                $blocked = true;

                return;
            }
            $visiting[$id] = true;
            foreach ($edges->get($id, collect()) as $edge) {
                $visit((int) $edge->prerequisite_id);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            $record = $all->get($id);
            $eligible = $available->has($id);
            if (! $eligible) {
                $blocked = true;
            }
            $steps[] = ['id' => $id, 'title' => $record?->title ?? 'Pelatihan tidak tersedia', 'available' => $eligible, 'cost' => $record ? (float) $record->cost : 0, 'duration_hours' => $record?->duration_hours ?? 0];
        };
        $visit($training->id);

        return ['steps' => $steps, 'blocked' => $blocked, 'total_cost' => array_sum(array_column($steps, 'cost')), 'total_hours' => array_sum(array_column($steps,'duration_hours'))];
    }
}
