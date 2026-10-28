<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Support\Publication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobPublicationController extends Controller
{
    public function update(Request $r, JobVacancy $job): RedirectResponse
    {
        $data = $r->validate(['status' => ['required', Rule::in(array_keys(JobVacancy::STATUSES))]]);
        if ($data['status'] === 'active' && ! Publication::jobs(false)->whereKey($job->id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Lowongan belum layak terbit. Periksa sumber, masa berlaku, deskripsi, dan persyaratan skill aktif.']);
        }
        $job->update(['status' => $data['status']]);

        return redirect()->route('admin.jobs.edit', $job)->with('success', 'Status publikasi lowongan diperbarui.');
    }
}
