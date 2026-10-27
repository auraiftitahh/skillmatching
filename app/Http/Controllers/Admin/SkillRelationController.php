<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SkillRelationController extends Controller
{
    public function index(Request $r, Skill $skill): View
    {
        $relations = DB::table('skill_relations as r')->join('skills as s', 's.id', '=', 'r.related_skill_id')->where('r.skill_id', $skill->id)->select('r.*', 's.name', 's.category', 's.is_active')->orderBy('s.name')->get();

        return view('admin.skills.relations', [...WorkspacePage::data($r, 'admin', 'skill', 'Relasi Keterampilan', route('admin.skills.edit', $skill)),
            'skill' => $skill, 'relations' => $relations, 'choices' => Skill::where('is_active', true)->where('id', '!=', $skill->id)->orderBy('name')->get()]);
    }

    public function store(Request $r, Skill $skill): RedirectResponse
    {
        $data = $r->validate(['related_skill_id' => ['required', 'integer', Rule::exists('skills', 'id')->where('is_active', true), Rule::notIn([$skill->id])], 'reason' => 'required|string|min:5|max:1500']);
        try {
            DB::transaction(function () use ($data, $skill): void {
                $locked = Skill::whereIn('id', [$skill->id, $data['related_skill_id']])->orderBy('id')->lockForUpdate()->get();
                abort_unless($locked->count() === 2 && $locked->every(fn ($row) => $row->is_active), 422, 'Kedua keterampilan harus aktif untuk membuat relasi.');
                if (DB::table('skill_relations')->where('skill_id', $skill->id)->where('related_skill_id', $data['related_skill_id'])->exists()) {
                    throw ValidationException::withMessages(['related_skill_id' => 'Relasi ini sudah tersedia.']);
                }
                DB::table('skill_relations')->insert(['skill_id' => $skill->id, ...$data, 'created_at' => now(), 'updated_at' => now()]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['related_skill_id' => 'Relasi ini sudah tersedia.']);
        }

        return redirect()->route('admin.skills.relations', $skill)->with('success', 'Relasi keterampilan tersimpan.');
    }

    public function destroy(Request $r, Skill $skill, int $relation): RedirectResponse
    {
        $record = DB::table('skill_relations')->where('skill_id', $skill->id)->where('id', $relation)->first();
        abort_unless($record, 404);
        DB::table('skill_relations')->where('id', $record->id)->delete();

        return redirect()->route('admin.skills.relations',$skill)->with('success','Relasi dihapus tanpa menghapus keterampilannya.');
    }
}
