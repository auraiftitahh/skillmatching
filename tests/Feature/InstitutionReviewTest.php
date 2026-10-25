<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\InstitutionStatusChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InstitutionReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function url(CertificationBody $body): string
    {
        return route('admin.institutions.decide', ['body' => $body->id]);
    }

    private function payload(string $status = 'pending', string $action = 'approve', array $extra = []): array
    {
        return array_merge(['expected_status' => $status, 'action' => $action, 'reason' => 'Informasi lembaga sudah ditinjau oleh Admin.'], $extra);
    }

    public function test_default_queue_shows_pending_bodies_and_search_and_status_filters_work(): void
    {
        $pending = CertificationBody::factory()->create(['name' => 'Lembaga Target']);
        $active = CertificationBody::factory()->active()->create(['name' => 'Lembaga Aktif']);
        $this->actingAs($this->admin())->get('/admin/persetujuan-lembaga')->assertOk()->assertViewHas('bodies', fn ($bodies) => $bodies->total() === 1 && $bodies->first()->id === $pending->id);
        $this->get('/admin/persetujuan-lembaga?status=all')->assertOk()->assertViewHas('bodies', fn ($bodies) => $bodies->total() === 2);
        $this->get('/admin/persetujuan-lembaga?status=active&q=AKTIF')->assertOk()->assertViewHas('bodies', fn ($bodies) => $bodies->first()->id === $active->id);
        $this->get('/admin/persetujuan-lembaga?status=all&q='.urlencode($active->user->email))->assertOk()->assertViewHas('bodies', fn ($bodies) => $bodies->first()->id === $active->id);
        $this->get('/admin/persetujuan-lembaga?q=TidakAda')->assertOk()->assertSee('Tidak ada lembaga yang sesuai');
    }

    public static function allowedTransitions(): array
    {
        return [
            ['pending', false, 'approve', 'active'],
            ['pending', false, 'reject', 'rejected'],
            ['rejected', false, 'approve', 'active'],
            ['active', true, 'deactivate', 'disabled'],
            ['disabled', true, 'activate', 'active'],
            ['disabled', false, 'approve', 'active'],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_decisions_record_status_actor_reason_and_preserve_account_eligibility(string $before, bool $previouslyApproved, string $action, string $after): void
    {
        $owner = User::factory()->institution()->inactive()->unverified()->create();
        $body = CertificationBody::factory()->create(['user_id' => $owner->id, 'status' => $before, 'verified_at' => $previouslyApproved ? now()->subDay() : null]);
        $previousApproval = $body->verified_at?->toDateTimeString();
        $admin = $this->admin();
        $this->actingAs($admin)->post($this->url($body), $this->payload($before, $action, ['is_active' => true, 'email_verified_at' => now()->toDateTimeString(), 'role' => 'admin']))->assertRedirect();
        $this->assertSame($after, $body->fresh()->status);
        $this->assertFalse($owner->fresh()->is_active);
        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
        $this->assertSame('sertifikasi', $owner->fresh()->role);
        $this->assertDatabaseHas('institution_status_changes', ['certification_body_id' => $body->id, 'actor_id' => $admin->id, 'actor_name' => $admin->name, 'action' => $action, 'before_status' => $before, 'after_status' => $after, 'reason' => 'Informasi lembaga sudah ditinjau oleh Admin.']);
        if ($action === 'approve') {
            $this->assertNotNull($body->fresh()->verified_at);
        } elseif (in_array($action, ['activate', 'deactivate'], true)) {
            $this->assertSame($previousApproval, $body->fresh()->verified_at?->toDateTimeString());
        } else {
            $this->assertNull($body->fresh()->verified_at);
        }
        Notification::assertNothingSent();
    }

    public static function forbiddenTransitions(): array
    {
        return [['pending', 'activate'], ['pending', 'deactivate'], ['rejected', 'activate'], ['active', 'approve'], ['active', 'reject'], ['disabled', 'activate']];
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_incompatible_or_unapproved_reactivation_is_rejected(string $status, string $action): void
    {
        $body = CertificationBody::factory()->create(['status' => $status, 'verified_at' => null]);
        $this->actingAs($this->admin())->post($this->url($body), $this->payload($status, $action))->assertSessionHasErrors('action');
        $this->assertSame($status, $body->fresh()->status);
        $this->assertDatabaseCount('institution_status_changes', 0);
    }

    public function test_reasons_and_expected_status_are_required_and_stale_or_repeated_decisions_are_rejected(): void
    {
        $body = CertificationBody::factory()->create();
        $this->actingAs($this->admin());
        foreach (['', '     ', 'abc', str_repeat('a', 501)] as $reason) {
            $this->post($this->url($body), $this->payload(extra: ['reason' => $reason]))->assertSessionHasErrors('reason');
        }
        $this->post($this->url($body), $this->payload(extra: ['expected_status' => null]))->assertSessionHasErrors('expected_status');
        $this->assertDatabaseCount('institution_status_changes', 0);
        $this->post($this->url($body), $this->payload())->assertRedirect();
        $this->post($this->url($body), $this->payload())->assertSessionHasErrors('action');
        $this->assertDatabaseCount('institution_status_changes', 1);
    }

    public static function nonAdminRoles(): array
    {
        return [['pelamar'], ['sertifikasi']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_read_or_decide_institution_reviews(string $role): void
    {
        $body = CertificationBody::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['/admin/persetujuan-lembaga', '/admin/persetujuan-lembaga/'.$body->id, '/admin/persetujuan-lembaga/999999'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post($this->url($body), $this->payload())->assertForbidden();
        $this->assertSame('pending', $body->fresh()->status);
    }

    public function test_guests_cannot_access_reviews(): void
    {
        $body = CertificationBody::factory()->create();
        $this->get('/admin/persetujuan-lembaga')->assertRedirect(route('login'));
        $this->post($this->url($body), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('institution_status_changes', 0);
    }

    public function test_history_is_scoped_and_escapes_reasons_and_filters_survive_back_navigation(): void
    {
        $body = CertificationBody::factory()->create();
        $other = CertificationBody::factory()->create();
        $this->actingAs($this->admin())->post($this->url($body), $this->payload(extra: ['reason' => '<script>alert(1)</script>']))->assertRedirect();
        $this->post($this->url($other), $this->payload(extra: ['reason' => 'Alasan lembaga lainnya.']))->assertRedirect();
        $this->get('/admin/persetujuan-lembaga/'.$body->id.'?status=all&q=contoh')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Alasan lembaga lainnya.')->assertSee('WIB')->assertSee(route('admin.institutions.index', ['q' => 'contoh', 'status' => 'all']));
    }

    public function test_approval_and_deactivation_change_existing_institution_session_access(): void
    {
        $owner = User::factory()->institution()->create(['password' => 'PasswordTesting123!']);
        $body = CertificationBody::factory()->create(['user_id' => $owner->id]);
        $this->post('/login', ['email' => $owner->email, 'password' => 'PasswordTesting123!'])->assertRedirect(route('institution.status'));
        $this->get('/sertifikasi/menunggu-verifikasi')->assertOk();
        $oldSession = session()->all();
        $this->post('/logout')->assertRedirect();
        $admin = $this->admin();
        $this->actingAs($admin)->post($this->url($body), $this->payload())->assertRedirect();
        Auth::forgetGuards();
        $this->withSession($oldSession)->get('/sertifikasi/dashboard')->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->actingAs($admin)->post($this->url($body), $this->payload('active', 'deactivate'))->assertRedirect();
        Auth::forgetGuards();
        $this->withSession($oldSession)->get('/sertifikasi/dashboard')->assertRedirect(route('institution.status'));
    }

    public function test_status_and_history_roll_back_together_on_write_failure(): void
    {
        $body = CertificationBody::factory()->create();
        $admin = $this->admin();
        InstitutionStatusChange::creating(function (): void {
            throw new RuntimeException('History unavailable.');
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post($this->url($body), $this->payload());
            $this->fail('History failure should abort the decision.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History unavailable.', $exception->getMessage());
        } finally {
            InstitutionStatusChange::flushEventListeners();
        }
        $this->assertSame('pending', $body->fresh()->status);
        $this->assertNull($body->fresh()->verified_at);
        $this->assertDatabaseCount('institution_status_changes', 0);
    }

    public function test_invalid_owner_role_and_missing_bodies_cannot_be_approved(): void
    {
        $body = CertificationBody::factory()->create(['user_id' => User::factory()]);
        $this->actingAs($this->admin())->post($this->url($body), $this->payload())->assertSessionHasErrors('action');
        $this->get('/admin/persetujuan-lembaga/999999')->assertNotFound();
        $this->post('/admin/persetujuan-lembaga/999999/keputusan', $this->payload())->assertNotFound();
        $this->getJson('/admin/persetujuan-lembaga?status=invalid')->assertUnprocessable();
        $this->assertDatabaseCount('institution_status_changes', 0);
    }
}
