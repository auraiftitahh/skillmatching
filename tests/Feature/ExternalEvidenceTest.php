<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_evidence_stays_visible_to_owner_and_never_updates_profile(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $applicant = User::factory()->create();
        $issuer = CertificationBody::factory()->active()->create();
        $other = CertificationBody::factory()->active()->create();
        $skill = Skill::factory()->create();
        $this->actingAs($applicant)->get(route('applicant.evidence.index'))->assertOk()->assertSee('Simpan pribadi');
        $this->post(route('applicant.evidence.store'), [
            'title' => 'Bukti demo ditolak', 'certification_body_id' => $issuer->id,
            'skill_id' => $skill->id, 'level' => 3,
            'file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $id = DB::table('external_certificates')->value('id');
        $this->actingAs($issuer->user)->get(route('institution.dashboard'))->assertOk()->assertViewHas('pendingEvidence', 1);
        $this->get(route('institution.evidence.index'))->assertOk()->assertSee('Bukti demo ditolak');
        $this->post(route('institution.evidence.decide', $id), ['decision' => 'rejected', 'reason' => 'Capaian belum dapat dibuktikan.'])->assertRedirect();
        $this->get(route('institution.history'))->assertOk()->assertSee('Capaian belum dapat dibuktikan.');
        $this->actingAs($other->user)->get(route('institution.history'))->assertOk()->assertDontSee('Capaian belum dapat dibuktikan.');
        $this->actingAs($applicant)->get(route('applicant.evidence.index'))->assertOk()->assertSee('Ditolak')->assertSee('Capaian belum dapat dibuktikan.');
        $this->assertDatabaseCount('user_skills', 0);
    }

    public function test_only_assigned_issuer_can_review_and_approve_supported_level(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $applicant = User::factory()->create();
        $issuer = CertificationBody::factory()->active()->create();
        $other = CertificationBody::factory()->active()->create();
        $skill = Skill::factory()->create();
        $data = ['title' => 'Bukti latihan', 'issuer_name' => 'Penerbit awal', 'skill_id' => $skill->id, 'level' => 3];
        $this->actingAs($applicant)->post(route('applicant.evidence.store'), [
            ...$data, 'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('applicant.evidence.index'));
        $id = DB::table('external_certificates')->value('id');
        $this->assertDatabaseHas('external_certificates', ['id' => $id, 'status' => 'unverified']);
        $this->assertDatabaseCount('user_skills', 0);
        $data['certification_body_id'] = $issuer->id;
        $update = route('applicant.evidence.update', $id);
        $this->patch($update, $data)->assertRedirect();
        $this->post(route('applicant.evidence.withdraw', $id))->assertRedirect();
        $document = route('institution.evidence.download', $id);
        $this->actingAs($issuer->user)->get($document)->assertNotFound();
        $this->actingAs($applicant)->patch($update, $data)->assertRedirect();
        $decision = route('institution.evidence.decide', $id);
        $payload = ['decision' => 'verified', 'reason' => 'Dokumen mendukung level tiga.'];
        $this->actingAs($other->user)->get($document)->assertNotFound();
        $this->post($decision, $payload)->assertNotFound();
        $this->actingAs($issuer->user)->get($document)->assertOk();
        $this->post($decision, $payload)->assertRedirect();
        $this->post($decision, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('skill_evidence', 1);
        $this->assertDatabaseHas('user_skills', ['user_id' => $applicant->id, 'skill_id' => $skill->id, 'level' => 3, 'verification_status' => 'verified']);

        $this->actingAs($applicant)->get(route('applicant.evidence.index'))->assertOk()->assertSee($payload['reason']);
        $this->patch($update, $data)->assertUnprocessable();
    }
}
