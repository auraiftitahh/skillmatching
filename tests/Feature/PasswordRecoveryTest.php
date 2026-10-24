<?php

namespace Tests\Feature;

use App\Models\CertificationBody;
use App\Models\User;
use App\Notifications\ResetAccountPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function account(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['password' => 'OldPasswordTesting123!'], $attributes));
    }

    private function payload(User $user, string $token, array $attributes = []): array
    {
        return array_merge([
            'email' => $user->email, 'token' => $token,
            'password' => 'NewPasswordTesting123!', 'password_confirmation' => 'NewPasswordTesting123!',
        ], $attributes);
    }

    private function link(User $user, string $token): string
    {
        return route('password.reset', ['token' => $token, 'email' => $user->email]);
    }

    public function test_login_links_to_forgot_password_and_form_has_csrf(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee('Kirim tautan pemulihan')->assertSee('name="_token"', false);
    }

    public function test_known_and_unknown_emails_receive_the_same_response(): void
    {
        $user = $this->account();
        $this->post('/forgot-password', ['email' => strtoupper($user->email)])->assertRedirect(route('password.request'));
        $knownMessage = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect(route('password.request'));
        $this->assertSame($knownMessage, session('status'));
        Notification::assertSentToTimes($user, ResetAccountPassword::class, 1);
    }

    public function test_reset_token_is_hashed_at_rest_and_a_second_request_is_throttled_per_email(): void
    {
        $user = $this->account();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $token = Notification::sent($user, ResetAccountPassword::class)->first()->token;
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertSentToTimes($user, ResetAccountPassword::class, 1);
    }

    public function test_valid_token_resets_password_rotates_remember_token_and_allows_login_with_new_password_only(): void
    {
        Event::fake([PasswordReset::class]);
        $user = $this->account();
        $previousRememberToken = $user->remember_token;
        $token = Password::createToken($user);
        $this->get($this->link($user, $token))->assertOk()->assertSee('Buat kata sandi baru')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post('/reset-password', $this->payload($user, $token))->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPasswordTesting123!', $user->fresh()->password));
        $this->assertNotSame($previousRememberToken, $user->fresh()->remember_token);
        $this->assertGuest();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Event::assertDispatchedTimes(PasswordReset::class, 1);
        $this->post('/login', ['email' => $user->email, 'password' => 'OldPasswordTesting123!'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPasswordTesting123!'])->assertRedirect(route('workspace', ['role' => 'pelamar', 'page' => 'dashboard']));
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $user = $this->account();
        $token = Password::createToken($user);
        $this->post('/reset-password', $this->payload($user, $token))->assertRedirect(route('login'));
        $this->post('/reset-password', $this->payload($user, $token, ['password' => 'AnotherPassword123!', 'password_confirmation' => 'AnotherPassword123!']))->assertStatus(422)->assertSee('Tautan tidak berlaku');
        $this->get($this->link($user, $token))->assertStatus(410);
        $this->assertTrue(Hash::check('NewPasswordTesting123!', $user->fresh()->password));
    }

    public function test_expired_token_is_rejected_on_form_and_submission(): void
    {
        $user = $this->account();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->get($this->link($user, $token))->assertStatus(410);
        $this->post('/reset-password', $this->payload($user, $token))->assertStatus(422);
        $this->assertTrue(Hash::check('OldPasswordTesting123!', $user->fresh()->password));
    }

    public function test_token_cannot_reset_a_different_account(): void
    {
        $owner = $this->account();
        $other = $this->account();
        $token = Password::createToken($owner);
        $this->get($this->link($other, $token))->assertStatus(410);
        $this->post('/reset-password', $this->payload($other, $token))->assertStatus(422);
        $this->assertTrue(Hash::check('OldPasswordTesting123!', $owner->fresh()->password));
        $this->assertTrue(Hash::check('OldPasswordTesting123!', $other->fresh()->password));
    }

    public function test_invalid_and_incomplete_reset_links_show_a_recovery_action(): void
    {
        $this->get('/reset-password/invalid')->assertStatus(410)->assertSee('Minta tautan baru');
        $this->get('/reset-password/invalid?email=unknown@example.test')->assertStatus(410);
        $this->get('/reset-password/invalid?email[]=invalid')->assertStatus(410);
    }

    public function test_password_confirmation_and_length_are_validated_without_consuming_the_token(): void
    {
        $user = $this->account();
        $token = Password::createToken($user);
        $this->from($this->link($user, $token))->post('/reset-password', $this->payload($user, $token, ['password_confirmation' => 'different']))->assertSessionHasErrors('password');
        $this->post('/reset-password', $this->payload($user, $token, ['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/reset-password', $this->payload($user, $token, ['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)]))->assertSessionHasErrors('password');
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->assertTrue(Hash::check('OldPasswordTesting123!', $user->fresh()->password));
    }

    public function test_reset_does_not_verify_email_reactivate_accounts_or_approve_institutions(): void
    {
        $user = $this->account(['role' => 'sertifikasi', 'email_verified_at' => null, 'is_active' => false]);
        $body = CertificationBody::factory()->create(['user_id' => $user->id]);
        $token = Password::createToken($user);
        $this->post('/reset-password', $this->payload($user, $token, ['is_active' => true, 'role' => 'admin', 'email_verified_at' => now()->toDateTimeString()]))->assertRedirect(route('login'));
        $this->assertFalse($user->fresh()->is_active);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertSame('sertifikasi', $user->fresh()->role);
        $this->assertSame('pending', $body->fresh()->status);
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPasswordTesting123!'])->assertSessionHasErrors('email');
    }

    public function test_forgot_password_endpoint_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertStatus(429)->assertSee('Terlalu banyak permintaan');
    }

    public function test_transport_failure_is_not_exposed_to_users_or_logs(): void
    {
        $user = $this->account();
        Log::spy();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('Private SMTP credential diagnostic'));
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee('Jika email terdaftar')->assertDontSee('Private SMTP credential diagnostic');
        Log::shouldHaveReceived('warning')->once()->with('Pengiriman email pemulihan belum berhasil.');
    }

    public function test_reset_email_escapes_names_and_contains_the_correct_expiring_url(): void
    {
        $user = $this->account(['name' => '<script>alert(1)</script>']);
        $token = Password::createToken($user);
        $message = (new ResetAccountPassword($token))->toMail($user);
        $this->assertSame($this->link($user, $token), $message->viewData['resetUrl']);
        $html = view($message->view, $message->viewData)->render();
        $this->assertStringContainsString('60 menit', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_requesting_a_new_link_after_cooldown_invalidates_the_previous_token(): void
    {
        $user = $this->account();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $oldToken = Notification::sent($user, ResetAccountPassword::class)->first()->token;
        $this->travel(61)->seconds();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $newToken = Notification::sent($user, ResetAccountPassword::class)->last()->token;
        $this->assertNotSame($oldToken, $newToken);
        $this->get($this->link($user, $oldToken))->assertStatus(410);
        $this->get($this->link($user, $newToken))->assertOk();
    }

    public function test_active_unverified_accounts_still_need_email_verification_after_reset(): void
    {
        $user = $this->account(['email_verified_at' => null]);
        $token = Password::createToken($user);
        $this->post('/reset-password', $this->payload($user, $token))->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPasswordTesting123!'])->assertRedirect(route('verification.notice'));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_old_authenticated_sessions_are_rejected_after_password_changes(): void
    {
        $user = $this->account();
        $this->post('/login', ['email' => $user->email, 'password' => 'OldPasswordTesting123!'])->assertRedirect();
        $this->get('/pelamar/dashboard')->assertOk();
        $oldSession = session()->all();
        $this->assertArrayHasKey('password_hash_web', $oldSession);
        $this->post('/logout')->assertRedirect();
        $token = Password::createToken($user);
        $this->post('/reset-password', $this->payload($user, $token))->assertRedirect(route('login'));
        Auth::forgetGuards();
        $this->withSession($oldSession)->get('/pelamar/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
