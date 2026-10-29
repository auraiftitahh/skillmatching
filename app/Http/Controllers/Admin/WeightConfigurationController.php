<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeightConfiguration;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WeightConfigurationController extends Controller
{
    public function edit(Request $r): View
    {
        return view('admin.weights.edit', [...WorkspacePage::data($r, 'admin', 'bobot', 'Pengaturan Bobot'), 'configuration' => WeightConfiguration::current()]);
    }

    public function store(Request $r): RedirectResponse
    {
        $data = $r->validate(['base_version' => 'required|integer|min:1', 'required_weight' => 'required|integer|between:1,5|gte:preferred_weight', 'preferred_weight' => 'required|integer|between:1,5', 'related_factor' => 'required|numeric|between:0,1|decimal:0,3', 'threshold' => 'required|numeric|between:0,100|decimal:0,2', 'reason' => 'required|string|min:8|max:2000']);
        DB::transaction(function () use ($r, $data): void {
            $state = DB::table('weight_configuration_state')->where('id', 1)->lockForUpdate()->first();
            $current = WeightConfiguration::findOrFail($state->active_configuration_id);
            abort_unless($current->version === (int) $data['base_version'], 409, 'Konfigurasi sudah berubah. Muat ulang dan periksa versi terbaru sebelum menerbitkan.');
            $configuration = WeightConfiguration::create([...array_diff_key($data, ['base_version' => true]), 'version' => $current->version + 1, 'created_by' => $r->user()->id]);
            DB::table('weight_configuration_state')->where('id', 1)->update(['active_configuration_id' => $configuration->id, 'updated_at' => now()]);
        });

        return redirect()->route('admin.weights.edit')->with('success', 'Versi bobot baru diterbitkan. Versi sebelumnya tetap tersimpan.');
    }

    public function history(Request $r): View
    {
        return view('admin.weights.history', [...WorkspacePage::data($r, 'admin', 'bobot', 'Riwayat Versi Bobot', route('admin.weights.edit')), 'configurations' => WeightConfiguration::with('author')->latest('version')->paginate(12), 'activeId' => WeightConfiguration::current()->id]);
    }

    public function show(Request $r, WeightConfiguration $configuration): View
    {
        return view('admin.weights.show', [...WorkspacePage::data($r, 'admin', 'bobot', 'Detail Versi Bobot', route('admin.weights.history')), 'configuration' => $configuration->load('author'), 'activeId' => WeightConfiguration::current()->id]);
    }
}
