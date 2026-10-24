<?php

namespace Tests\Feature;

use App\Models\AccountStatusChange;
use App\Models\CertificationBody;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountManagementTest extends TestCase
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

    private function updateUrl(User $user): string
    {
        return route('admin.accounts.status', ['user' => $user->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['action' => 'deactivate', 'expected_is_active' => true, 'reason' => 'Alasan pengujian status akun.'], $overrides);
    }

    public function test_admin_can_search_by_name_email_and_institution_name(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Nama Sasaran', 'email' => 'sasaran@example.test']);
        $body = CertificationBody::factory()->create(['name' => 'Lembaga Unik ABC']);
        $this->actingAs($admin)->get('/admin/akun?q=NAMA%20SASARAN')->assertOk()->assertViewHas('accounts', fn ($accounts) => $accounts->total() === 1 && $accounts->first()->id === $target->id);
        $this->get('/admin/akun?q=sasaran%40example.test')->assertOk()->assertViewHas('accounts', fn ($accounts) => $accounts->first()->id === $target->id);
        $this->get('/admin/akun?q=Lembaga%20Unik')->assertOk()->assertViewHas('accounts', fn ($accounts) => $accounts->first()->id === $body->user_id);
        $this->get('/admin/akun?q=TidakAdaHasil')->assertOk()->assertSee('Tidak ada akun yang sesuai');
    }

    public function test_role_status_and_email_filters_can_be_combined(): void
    {
        $target = User::factory()->inactive()->unverified()->create();
        User::factory()->create();
        $this->actingAs($this->admin())->get('/admin/akun?role=pelamar&status=inactive&verification=unverified')
            ->assertOk()->assertViewHas('accounts', fn ($accounts) => $accounts->total() === 1 && $accounts->first()->id === $target->id);
    }

    public function test_search_wildcards_are_treated_as_literal_characters(): void
    {
        $target = User::factory()->create(['name' => 'Kesiapan 100%']);
        User::factory()->create(['name' => 'Pelamar Lain']);
        $this->actingAs($this->admin())->get('/admin/akun?q=%25')->assertOk()->assertViewHas('accounts', fn ($accounts) => $accounts->total() === 1 && $accounts->first()->id === $target->id);
    }

    public function test_pagination_and_back_links_preserve_filters(): void
    {
        User::factory()->count(20)->create();
        $this->actingAs($this->admin())->get('/admin/akun?role=pelamar&page=2')->assertOk()
            ->assertViewHas('accounts', fn ($accounts) => $accounts->count() === 5 && $accounts->total() === 20)
            ->assertSee('role=pelamar', false);
        $user = User::where('role', 'pelamar')->firstOrFail();
        $this->get('/admin/akun/'.$user->id.'?role=pelamar&page=2')->assertOk()->assertSee(route('admin.accounts.index', ['role' => 'pelamar', 'page' => 2]));
        $this->get('/admin/akun?role=pelamar&page=999')->assertRedirect(route('admin.accounts.index', ['role' => 'pelamar', 'page' => 2]));
    }

    public static function nonAdminRoles(): array
    {
        return [['pelamar'], ['sertifikasi']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_read_or_modify_accounts_even_by_direct_url(string $role): void
    {
        $target = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (['/admin/akun', '/admin/akun/'.$target->id, '/admin/akun/999999'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->patch($this->updateUrl($target), $this->payload())->assertForbidden();
        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseCount('account_status_changes', 0);
    }

    public function test_guests_are_redirected_to_login_without_modifying_data(): void
    {
        $target = User::factory()->create();
        $this->get('/admin/akun')->assertRedirect(route('login'));
        $this->patch($this->updateUrl($target), $this->payload())->assertRedirect(route('login'));
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_deactivation_records_actor_reason_and_before_after_status_atomically(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->patch($this->updateUrl($target), $this->payload())->assertRedirect(route('admin.accounts.show', ['user' => $target->id]));
        $this->assertFalse($target->fresh()->is_active);
        $this->assertDatabaseHas('account_status_changes', ['user_id' => $target->id, 'actor_id' => $admin->id, 'actor_name' => $admin->name, 'before_is_active' => true, 'after_is_active' => false, 'reason' => 'Alasan pengujian status akun.']);
        $this->get('/admin/akun/'.$target->id)->assertOk()->assertSee('Aktif → Nonaktif')->assertSee($admin->name)->assertSee('WIB');
        Notification::assertNothingSent();
    }

    public function test_account_activation_does_not_verify_email_or_approve_institutions_or_change_roles(): void
    {
        $body = CertificationBody::factory()->create(['user_id' => User::factory()->institution()->inactive()->unverified()]);
        $target = $body->user;
        $this->actingAs($this->admin())->patch($this->updateUrl($target), $this->payload(['action' => 'activate', 'expected_is_active' => false, 'role' => 'admin', 'email_verified_at' => now()->toDateTimeString()]))->assertRedirect();
        $this->assertTrue($target->fresh()->is_active);
        $this->assertFalse($target->fresh()->hasVerifiedEmail());
        $this->assertSame('sertifikasi', $target->fresh()->role);
        $this->assertSame('pending', $body->fresh()->status);
    }

    public function test_reason_is_required_and_whitespace_or_short_reasons_do_not_change_accounts(): void
    {
        $target = User::factory()->create();
        $this->actingAs($this->admin());
        foreach (['', '     ', 'abc', str_repeat('a', 501)] as $reason) {
            $this->patch($this->updateUrl($target), $this->payload(['reason' => $reason]))->assertSessionHasErrors('reason');
        }
        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseCount('account_status_changes', 0);
    }

    public function test_admin_accounts_including_the_current_admin_are_read_only(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $this->actingAs($admin);
        foreach ([$admin, $other] as $target) {
            $this->get('/admin/akun/'.$target->id)->assertOk()->assertSee('Status akun Admin tidak dapat diubah')->assertDontSee($this->updateUrl($target), false);
            $this->patch($this->updateUrl($target), $this->payload())->assertForbidden();
            $this->assertTrue($target->fresh()->is_active);
        }
        $this->assertDatabaseCount('account_status_changes', 0);
    }

    public function test_stale_or_repeated_status_changes_are_rejected_without_duplicate_history(): void
    {
        $target = User::factory()->create();
        $this->actingAs($this->admin())->patch($this->updateUrl($target), $this->payload())->assertRedirect();
        $this->patch($this->updateUrl($target), $this->payload())->assertSessionHasErrors('action');
        $this->patch($this->updateUrl($target), $this->payload(['expected_is_active' => false]))->assertSessionHasErrors('action');
        $this->assertDatabaseCount('account_status_changes', 1);
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_history_is_scoped_to_the_account_and_escapes_user_entered_reasons(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($this->admin())->patch($this->updateUrl($target), $this->payload(['reason' => '<script>alert(1)</script>']))->assertRedirect();
        $this->patch($this->updateUrl($other), $this->payload(['reason' => 'Riwayat akun lainnya.']))->assertRedirect();
        $this->get('/admin/akun/'.$target->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Riwayat akun lainnya.');
    }

    public function test_admin_deactivation_revokes_a_previously_authenticated_user_session(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['password' => 'PasswordTesting123!']);
        $this->post('/login', ['email' => $target->email, 'password' => 'PasswordTesting123!'])->assertRedirect();
        $this->get('/pelamar/dashboard')->assertOk();
        $oldSession = session()->all();
        $this->post('/logout')->assertRedirect();
        $this->actingAs($admin)->patch($this->updateUrl($target), $this->payload())->assertRedirect();
        Auth::forgetGuards();
        $this->withSession($oldSession)->get('/pelamar/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_zero_is_a_valid_search_term(): void
    {
        $admin = $this->admin();
        $admin->update(['name' => 'Administrator', 'email' => 'admin@example.test']);
        $target = User::factory()->create(['name' => 'Nama Nol 0', 'email' => 'nol@example.test']);
        User::factory()->create(['name' => 'Nama Satu', 'email' => 'satu@example.test']);
        $this->actingAs($admin)->get('/admin/akun?q=0')->assertOk()->assertViewHas('accounts', fn ($accounts) => $accounts->total() === 1 && $accounts->first()->id === $target->id);
    }

    public function test_status_update_rolls_back_when_history_cannot_be_saved(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        AccountStatusChange::creating(function (): void {
            throw new \RuntimeException('History write failed.');
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->patch($this->updateUrl($target), $this->payload());
            $this->fail('A failed history write must abort the status change.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('History write failed.', $exception->getMessage());
        } finally {
            AccountStatusChange::flushEventListeners();
        }
        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseCount('account_status_changes', 0);
    }

    public function test_missing_accounts_and_invalid_filters_fail_without_mutations(): void
    {
        $this->actingAs($this->admin())->get('/admin/akun/999999')->assertNotFound();
        $this->getJson('/admin/akun?role=invalid')->assertUnprocessable();
        $this->getJson('/admin/akun?q[]=invalid')->assertUnprocessable();
        $this->getJson('/admin/akun?page=-1')->assertUnprocessable();
        $this->patch('/admin/akun/999999/status', $this->payload())->assertNotFound();
        $this->assertDatabaseCount('account_status_changes', 0);
    }
}
