<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\TrainingSubmission;
use App\Services\TrainingReview;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingReviewController extends Controller
{
    public function index(Request $r): View
    {
        $data = $r->validate(['status' => ['nullable', Rule::in(array_keys(TrainingSubmission::LABELS))]]);
        $status = $data['status'] ?? 'pending';

        return view('admin.training-reviews.index', [...WorkspacePage::data($r, 'admin', 'pengajuan-pelatihan', 'Tinjauan Pelatihan'), 'status' => $status, 'submissions' => TrainingSubmission::with('body')->where('status', $status)->latest('submitted_at')->paginate(10)->withQueryString()]);
    }

    public function show(Request $r, int $submission): View
    {
        return view('admin.training-reviews.show', [...WorkspacePage::data($r, 'admin', 'pengajuan-pelatihan', 'Detail Tinjauan Pelatihan', route('admin.training-reviews.index')), 'submission' => TrainingSubmission::with(['body', 'reviewer'])->findOrFail($submission), 'skills' => Skill::where('is_active', true)->orderBy('name')->get()]);
    }

    public function decide(Request $r, int $submission, TrainingReview $review): RedirectResponse
    {
        $data = $r->validate(['decision' => ['required', Rule::in(['approved', 'revision', 'rejected'])], 'review_note' => 'required|string|min:5|max:2000', 'mapping' => 'nullable|array|max:20', 'mapping.*' => ['nullable', 'integer', Rule::exists('skills', 'id')->where('is_active', true)]]);
        $review->decide($submission, $r->user(), $data);

        return redirect()->route('admin.training-reviews.show',$submission)->with('success','Keputusan tersimpan dan dapat dilihat oleh lembaga.');
    }
}
