<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\InstitutionStatusChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstitutionAccountStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function decision(CertificationBody $body, string $reason): InstitutionStatusChange
    {
        $actor = User::factory()->admin()->create();

        return InstitutionStatusChange::create([
            'certification_body_id' => $body->id, 'actor_id' => $actor->id,
            'body_name' => $body->name, 'owner_email' => $body->user->email,
            'actor_name' => $actor->name, 'action' => 'reject',
            'before_status' => 'pending', 'after_status' => 'rejected', 'reason' => $reason,
        ]);
    }

    public static function eligibilityStates(): array
    {
        $cases = [];
        foreach ([true, false] as $active) {
            foreach ([true, false] as $verified) {
                foreach (['pending', 'active', 'rejected', 'disabled', null] as $status) {
                    $cases[] = [$active, $verified, $status];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('eligibilityStates')]
    public function test_account_email_and_institution_states_produce_the_correct_access(bool $active, bool $verified, ?string $status): void
    {
        $user = User::factory()->institution()->create(['is_active' => $active, 'email_verified_at' => $verified ? now() : null]);
        if ($status !== null) {
            CertificationBody::factory()->create(['user_id' => $user->id, 'status' => $status]);
        }
        $response = $this->actingAs($user)->get('/sertifikasi/status-akun');
        if (! $active) {
            $response->assertRedirect(route('login'));
            $this->assertGuest();
        } elseif (! $verified) {
            $response->assertRedirect(route('verification.notice'));
            $this->assertGuest();
        } else {
            $response->assertOk()->assertSee('Status akun lembaga')->assertSee($user->email)->assertSee('Cek status terbaru');
            if ($status === 'active') {
                $response->assertSee('Buka dashboard');
                $this->get('/sertifikasi/pengajuan')->assertOk();
                $this->get('/sertifikasi/menunggu-verifikasi')->assertRedirect(route('workspace', ['role' => 'sertifikasi', 'page' => 'dashboard']));
            } else {
                $response->assertDontSee('Buka dashboard');
                $this->get('/sertifikasi/pengajuan')->assertRedirect(route('institution.status'));
            }
        }
    }

    public function test_only_the_owners_institution_and_decisions_are_visible_even_if_other_ids_are_supplied(): void
    {
        $own = CertificationBody::factory()->create(['name' => 'Lembaga Milik Sendiri', 'status' => 'rejected']);
        $other = CertificationBody::factory()->create(['name' => 'Lembaga Orang Lain', 'status' => 'rejected']);
        $this->decision($own, 'Alasan keputusan lembaga sendiri.');
        $this->decision($other, 'Alasan rahasia lembaga lainnya.');
        $this->actingAs($own->user)->get('/sertifikasi/status-akun?body='.$other->id.'&user_id='.$other->user_id)
            ->assertOk()->assertSee('Lembaga Milik Sendiri')->assertSee('Alasan keputusan lembaga sendiri.')
            ->assertDontSee('Lembaga Orang Lain')->assertDontSee('Alasan rahasia lembaga lainnya.')->assertDontSee($other->user->email);
        $this->get('/sertifikasi/menunggu-verifikasi?body='.$other->id)->assertOk()->assertDontSee('Alasan rahasia lembaga lainnya.');
    }

    public function test_reason_and_actor_are_escaped_and_dates_are_readable(): void
    {
        $body = CertificationBody::factory()->create(['status' => 'rejected']);
        $change = $this->decision($body, '<script>alert(1)</script>');
        $change->update(['actor_name' => '<script>actor</script>']);
        $this->actingAs($body->user)->get('/sertifikasi/status-akun')->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<script>actor</script>', false)->assertSee('WIB');
    }

    public function test_missing_profile_and_empty_history_have_honest_next_steps(): void
    {
        $user = User::factory()->institution()->create();
        $other = CertificationBody::factory()->create(['status' => 'rejected']);
        $this->decision($other, 'Keputusan lembaga lain tidak boleh tampil.');
        $this->actingAs($user)->get('/sertifikasi/status-akun')->assertOk()->assertSee('Profil lembaga belum tersedia')
            ->assertSee('Belum ada catatan keputusan')->assertDontSee('Keputusan lembaga lain tidak boleh tampil.');
    }

    public function test_pagination_is_scoped_and_out_of_range_pages_are_redirected(): void
    {
        $body = CertificationBody::factory()->create(['status' => 'rejected']);
        for ($index = 0; $index < 12; $index++) {
            $this->decision($body, 'Catatan lembaga nomor '.$index);
        }
        $this->actingAs($body->user)->get('/sertifikasi/status-akun?history_page=2')->assertOk()->assertViewHas('changes', fn ($changes) => $changes->count() === 2 && $changes->total() === 12);
        $this->get('/sertifikasi/status-akun?history_page=99')->assertRedirect(route('institution.account-status', ['history_page' => 2]));
        $this->getJson('/sertifikasi/status-akun?history_page[]=invalid')->assertUnprocessable();
    }

    public static function otherRoles(): array
    {
        return [['pelamar'], ['admin']];
    }

    #[DataProvider('otherRoles')]
    public function test_other_roles_cannot_read_institution_status(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get('/sertifikasi/status-akun')->assertForbidden();
    }

    public function test_guests_cannot_read_status_and_owners_cannot_change_approval(): void
    {
        $body = CertificationBody::factory()->create();
        $this->get('/sertifikasi/status-akun')->assertRedirect(route('login'));
        $this->actingAs($body->user)->post('/sertifikasi/status-akun', ['status' => 'active'])->assertStatus(405);
        $this->post('/admin/persetujuan-lembaga/'.$body->id.'/keputusan', ['action' => 'approve', 'expected_status' => 'pending', 'reason' => 'Mencoba menyetujui sendiri.'])->assertForbidden();
        $this->assertSame('pending', $body->fresh()->status);
        $this->assertDatabaseCount('institution_status_changes', 0);
    }

    public function test_active_institutions_can_open_status_from_their_dashboard_navigation(): void
    {
        $body = CertificationBody::factory()->active()->create();
        $this->actingAs($body->user)->get('/sertifikasi/dashboard')->assertOk()->assertSee('/sertifikasi/status-akun')->assertSee('Status Akun Lembaga');
        $this->get('/sertifikasi/status-akun')->assertOk()->assertSee('Kembali ke dashboard')->assertSee('Buka dashboard');
    }

    public function test_outdated_history_is_not_presented_as_the_current_status_reason(): void
    {
        $body = CertificationBody::factory()->create(['status' => 'pending']);
        $this->decision($body, 'Catatan keputusan lama.');
        $this->actingAs($body->user)->get('/sertifikasi/status-akun')->assertOk()->assertSee('Catatan keputusan terakhir belum sesuai dengan status saat ini');
    }

    public function test_institution_can_still_log_out_from_the_restricted_status_page(): void
    {
        $body = CertificationBody::factory()->create();
        $this->actingAs($body->user)->get('/sertifikasi/status-akun')->assertOk()->assertSee('Keluar dari akun');
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        Notification::assertNothingSent();
    }
}
