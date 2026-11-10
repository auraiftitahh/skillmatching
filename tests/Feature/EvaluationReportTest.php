<?php

namespace Tests\Feature;

use App\Models\JobVacancy;
use App\Models\User;
use App\Models\WeightConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EvaluationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_report_and_export_share_period_version_and_access_rules(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $job = JobVacancy::factory()->published()->create(['title' => 'Posisi evaluasi']);
        $skill = $job->skills()->firstOrFail();
        $version = WeightConfiguration::current();
        $this->actingAs($applicant)->post(route('applicant.matching.feedback', $job->id), [
            'rating' => 'relevant', 'comment' => 'Cocok untuk profil uji.',
        ])->assertRedirect();
        $this->get(route('admin.evaluation.export'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.evaluation.feedback'))->assertOk()->assertSee('Cocok untuk profil uji.');
        $this->post(route('admin.evaluation.profiles.store'), [
            'code' => '=UJI', 'group' => 'Demo', 'levels' => [$skill->id => 2],
        ])->assertRedirect();
        $profile = DB::table('evaluation_profiles')->value('id');
        $this->post(route('admin.evaluation.ratings.store'), [
            'evaluation_profile_id' => $profile, 'job_vacancy_id' => $job->id, 'expert_rating' => 4,
        ])->assertRedirect();
        $today = now('Asia/Jakarta')->toDateString();
        $filters = ['from' => $today, 'to' => $today, 'version' => $version->id];
        $this->get(route('admin.evaluation.report', $filters))->assertOk()->assertSee('1 penilaian')->assertSee('Posisi evaluasi');
        $csv = $this->get(route('admin.evaluation.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=UJI", $csv);
        $this->assertStringContainsString('Posisi evaluasi', $csv);

        $excluded = ['to' => now('Asia/Jakarta')->subDay()->toDateString(), 'version' => $version->id];
        $this->get(route('admin.evaluation.report', $excluded))->assertOk()->assertSee('0 penilaian');
        $this->assertStringNotContainsString('Posisi evaluasi', $this->get(route('admin.evaluation.export', $excluded))->assertOk()->streamedContent());
    }
}
