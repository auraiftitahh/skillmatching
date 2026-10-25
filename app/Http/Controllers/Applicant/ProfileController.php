<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\UpdateProfileRequest;
use App\Support\WorkspaceNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('applicant.profile', [
            'user' => $request->user(), 'pageTitle' => 'Profil Saya', 'roleLabel' => 'Pelamar',
            'navigation' => WorkspaceNavigation::items('pelamar', 'profil'),
            'homeUrl' => route('workspace', ['role' => 'pelamar', 'page' => 'dashboard']),
            'userName' => $request->user()->name,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        foreach (['phone', 'domicile', 'employment_status'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }
        $request->user()->update($data);

        return redirect()->route('applicant.profile.edit')->with('success', 'Profil Anda berhasil diperbarui.');
    }
}
