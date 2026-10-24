<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginAndRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function account(string $role = 'pelamar'): User
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'PasswordTesting123!']);
        if ($role === 'sertifikasi') {
            CertificationBody::factory()->active()->create(['user_id' => $user->id]);
        }

        return $user;
    }

    public static function roles(): array
    {
        return [['pelamar'], ['admin'], ['sertifikasi']];
    }

    #[DataProvider('roles')]
    public function test_eligible_accounts_login_to_their_own_dashboard(string $role): void
    {
        $user = $this->account($role);
        $this->get('/login')->assertOk()->assertSee('autocomplete="current-password"', false)->assertSee('name="_token"', false);
        $previousId = session()->getId();
        $this->withSession(['url.intended' => 'https://example.test/untrusted'])->post('/login', ['email' => strtoupper($user->email), 'password' => 'PasswordTesting123!', 'role' => 'admin'])
            ->assertRedirect(route('workspace', ['role' => $role, 'page' => 'dashboard']));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousId, session()->getId());
        $this->assertFalse(session()->has('url.intended'));
        $this->get('/'.$role.'/dashboard')->assertOk()->assertSee($user->name)->assertSee('Keluar dari akun')->assertDontSee('Pratinjau antarmuka')->assertHeader('Cache-Control', 'no-store, private');
        Notification::assertNothingSent();
    }

    public function test_unknown_email_and_incorrect_password_have_the_same_error(): void
    {
        $user = $this->account();
        $this->post('/login', ['email' => $user->email, 'password' => 'WrongPassword123!'])->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.']);
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'WrongPassword123!'])->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.']);
        $this->assertGuest();
        $this->get('/login')->assertDontSee('WrongPassword123!');
    }

    public function test_unverified_accounts_are_sent_to_verification_without_authenticated_session(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'PasswordTesting123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'PasswordTesting123!'])->assertRedirect(route('verification.notice'));
        $this->assertGuest();
        $this->get('/verification-notice')->assertOk()->assertSee('Verifikasi email terlebih dahulu');
        Notification::assertNothingSent();
    }

    public function test_inactive_accounts_cannot_login(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'PasswordTesting123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'PasswordTesting123!'])->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_limited_after_five_failed_attempts_and_recovers_after_cooldown(): void
    {
        $user = $this->account();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'WrongPassword123!'])->assertRedirect(route('login'));
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'PasswordTesting123!'])->assertStatus(429)->assertSee('Terlalu banyak percobaan');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'PasswordTesting123!'])->assertRedirect(route('workspace', ['role' => 'pelamar', 'page' => 'dashboard']));
    }

    public static function restrictedInstitutions(): array
    {
        return [['pending'], ['rejected'], ['disabled'], [null]];
    }

    #[DataProvider('restrictedInstitutions')]
    public function test_institutions_without_operational_approval_only_reach_the_status_page(?string $status): void
    {
        $user = User::factory()->institution()->create(['password' => 'PasswordTesting123!']);
        if ($status !== null) {
            CertificationBody::factory()->create(['user_id' => $user->id, 'status' => $status]);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'PasswordTesting123!'])->assertRedirect(route('institution.status'));
        $this->assertAuthenticatedAs($user);
        $this->get('/sertifikasi/menunggu-verifikasi')->assertOk()->assertSee('Status akun lembaga')->assertSee('Keluar dari akun');
        foreach (['dashboard', 'pengajuan', 'peserta', 'bukti-eksternal'] as $page) {
            $this->get('/sertifikasi/'.$page)->assertRedirect(route('institution.status'));
        }
    }

    public static function forbiddenRoles(): array
    {
        return [['pelamar', 'admin'], ['pelamar', 'sertifikasi'], ['admin', 'pelamar'], ['admin', 'sertifikasi'], ['sertifikasi', 'pelamar'], ['sertifikasi', 'admin']];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_cross_role_urls_are_forbidden(string $role, string $target): void
    {
        $this->actingAs($this->account($role))->get('/'.$target.'/dashboard')->assertForbidden()->assertSee('Akses tidak tersedia');
    }

    public function test_guests_are_redirected_to_login_for_all_operational_roles(): void
    {
        foreach (['pelamar', 'admin', 'sertifikasi'] as $role) {
            $this->get('/'.$role.'/dashboard')->assertRedirect(route('login'));
        }
    }

    public function test_deactivation_takes_effect_on_the_next_request_of_an_existing_session(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('/pelamar/dashboard')->assertOk();
        User::whereKey($user->id)->update(['is_active' => false]);
        $this->get('/pelamar/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deleted_accounts_cannot_keep_using_an_existing_session(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('/pelamar/dashboard')->assertOk();
        User::whereKey($user->id)->delete();
        $this->get('/pelamar/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_removing_email_verification_revokes_an_existing_session(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('/pelamar/dashboard')->assertOk();
        User::whereKey($user->id)->update(['email_verified_at' => null]);
        $this->get('/pelamar/dashboard')->assertRedirect(route('verification.notice'));
        $this->assertGuest();
    }

    public function test_disabling_an_institution_revokes_operational_access_without_changing_its_email(): void
    {
        $user = $this->account('sertifikasi');
        $this->actingAs($user)->get('/sertifikasi/dashboard')->assertOk();
        $user->certificationBody->update(['status' => 'disabled']);
        $this->get('/sertifikasi/dashboard')->assertRedirect(route('institution.status'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_logout_invalidates_session_data_and_only_accepts_post(): void
    {
        $user = $this->account();
        $this->actingAs($user)->withSession(['private_marker' => 'private'])->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertFalse(session()->has('private_marker'));
        $this->get('/pelamar/dashboard')->assertRedirect(route('login'));
        $this->get('/logout')->assertStatus(405);
    }

    public function test_authenticated_login_page_and_root_redirect_to_the_correct_role(): void
    {
        $user = $this->account('admin');
        $destination = route('workspace', ['role' => 'admin', 'page' => 'dashboard']);
        $this->actingAs($user)->get('/login')->assertRedirect($destination);
        $this->get('/')->assertRedirect($destination);
    }

    public function test_an_active_institution_does_not_stay_on_the_pending_status_page(): void
    {
        $user = $this->account('sertifikasi');
        $this->actingAs($user)->get('/sertifikasi/menunggu-verifikasi')->assertRedirect(route('workspace', ['role' => 'sertifikasi', 'page' => 'dashboard']));
    }

    public function test_actual_workspace_navigation_stays_in_the_users_role_and_unknown_pages_return_404(): void
    {
        $this->actingAs($this->account())->get('/pelamar/profil')->assertOk()->assertSee('/pelamar/dashboard')->assertDontSee('/preview/pelamar');
        $this->get('/pelamar/not-found')->assertNotFound();
    }
}
