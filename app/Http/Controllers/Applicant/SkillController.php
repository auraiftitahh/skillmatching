<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\UserSkill;
use App\Services\ApplicantSkills;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillController extends Controller
{
    public const LEVELS = [0 => 'Tidak dipilih', 1 => 'Dasar', 2 => 'Menengah', 3 => 'Mahir', 4 => 'Ahli'];

    public function edit(Request $r): View
    {
        $own = UserSkill::with('skill')->where('user_id', $r->user()->id)->get();

        return view('applicant.skills', [...WorkspacePage::data($r, 'pelamar', 'skill', 'Keterampilan Saya', route('applicant.profile.edit')),
            'skills' => Skill::where('is_active', true)->orderBy('category')->orderBy('name')->get(), 'own' => $own->keyBy('skill_id'), 'levels' => self::LEVELS,
            'supported' => DB::table('skill_evidence')->where('user_id', $r->user()->id)->selectRaw('skill_id, MAX(level) AS level')->groupBy('skill_id')->pluck('level', 'skill_id'),
            'inactive' => $own->filter(fn ($s) => ! $s->skill->is_active)]);
    }

    public function update(Request $r, ApplicantSkills $skills): RedirectResponse
    {
        $data = $r->validate(['levels' => 'required|array|max:1000', 'levels.*' => 'required|integer|between:0,4']);
        $skills->saveSelfReported($r->user(), $data['levels']);

        return redirect()->route('applicant.skills.edit')->with('success','Pilihan keterampilan tersimpan. Level yang Anda isi sendiri bukan verifikasi lembaga.');
    }
}
