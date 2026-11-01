<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\TrainingSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_scoped_to_own_body_and_pending_body_cannot_operate(): void
    {
        $this->withoutVite();
        $body = CertificationBody::factory()->active()->create();
        $own = TrainingSubmission::factory()->create(['certification_body_id' => $body->id, 'status' => 'revision', 'review_note' => 'Perbaiki uraian capaian milik lembaga sendiri.']);
        $other = TrainingSubmission::factory()->create(['review_note' => 'Catatan rahasia lembaga lain.']);
        $this->actingAs($body->user)->get('/sertifikasi/dashboard')->assertOk()->assertSee($body->name)->assertSee($own->review_note)->assertDontSee($other->review_note)->assertViewHas('publishedCount', 0);
        $body->update(['status' => 'pending']);
        $this->get('/sertifikasi/dashboard')->assertRedirect(route('institution.status'));
    }
}
