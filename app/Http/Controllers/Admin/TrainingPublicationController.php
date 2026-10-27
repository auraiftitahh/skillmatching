<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Services\TrainingPrerequisites;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrainingPublicationController extends Controller
{
    public function index(Request $r): View
    {
        return view('admin.trainings.index', [...WorkspacePage::data($r, 'admin', 'pengajuan-pelatihan', 'Publikasi dan Prasyarat Pelatihan', route('admin.training-reviews.index')), 'trainings' => Training::with('body')->latest('id')->paginate(12)]);
    }

    public function edit(Request $r, Training $training): View
    {
        $selected = DB::table('training_prerequisites')->where('training_id', $training->id)->pluck('prerequisite_id')->all();

        return view('admin.trainings.publication', [...WorkspacePage::data($r, 'admin', 'pengajuan-pelatihan', 'Atur Publikasi dan Prasyarat', route('admin.trainings.index')), 'training' => $training,
            'choices' => Training::where(fn ($query) => $query->whereIn('id', Publication::trainings()->select('trainings.id'))->orWhereIn('id', $selected))->where('id', '!=', $training->id)->orderBy('title')->get(),
            'selected' => $selected, 'eligible' => Publication::trainings(false)->whereKey($training->id)->exists()]);
    }

    public function update(Request $r, Training $training, TrainingPrerequisites $prerequisites): RedirectResponse
    {
        $data = $r->validate(['prerequisites' => 'nullable|array|max:50', 'prerequisites.*' => 'required|integer|distinct|exists:trainings,id']);
        $prerequisites->save($training, $data['prerequisites'] ?? []);

        return redirect()->route('admin.trainings.publication', $training)->with('success', 'Prasyarat pelatihan tersimpan.');
    }

    public function status(Request $r, Training $training): RedirectResponse
    {
        $data = $r->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        if ($data['status'] === 'active' && ! Publication::trainings(false)->whereKey($training->id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Pelatihan belum layak terbit. Periksa persetujuan, akun penyelenggara, dan capaian skill aktif.']);
        }
        $training->update(['status' => $data['status']]);

        return redirect()->route('admin.trainings.publication', $training)->with('success', 'Status publikasi diperbarui tanpa menghapus data peserta.');
    }
}
