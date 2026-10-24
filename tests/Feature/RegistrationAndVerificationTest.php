<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Models\CertificationBody;
use App\Models\User;
use App\Notifications\VerifyAccountEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class RegistrationAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pelamar Baru', 'email' => 'pelamar@example.test',
            'role' => 'pelamar', 'password' => 'PasswordTesting123!',
            'password_confirmation' => 'PasswordTesting123!',
        ], $overrides);
    }

    private function link(User $user, int $minutes = 60, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id, 'hash' => $hash ?? sha1($user->email),
        ]);
    }

    public function test_registration_form_has_csrf_role_selection_and_institution_fields_hidden_for_applicants(): void
    {
        $this->get('/register')->assertOk()->assertSee('name="_token"', false)
            ->assertSee('id="institution-fields" class="institution-fields"  hidden disabled', false);
        $this->get('/register?role=sertifikasi')->assertOk()->assertSee('value="sertifikasi" selected', false);
    }

    public function test_applicant_registration_normalizes_email_ignores_privileged_fields_and_sends_one_notification(): void
    {
        $this->post('/register', $this->payload([
            'email' => '  PELAMAR@Example.test  ', 'is_active' => false,
            'email_verified_at' => now()->toDateTimeString(), 'body_name' => 'Ignored',
        ]))->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'pelamar@example.test')->firstOrFail();
        $this->assertSame('pelamar', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('PasswordTesting123!', $user->password));
        $this->assertDatabaseCount('certification_bodies', 0);
        $this->assertGuest();
        Notification::assertSentToTimes($user, VerifyAccountEmail::class, 1);
    }

    public function test_institution_registration_creates_a_pending_profile_without_approval(): void
    {
        $this->post('/register', $this->payload(['role' => 'sertifikasi', 'body_name' => 'Lembaga Baru', 'contact' => '08123456789', 'status' => 'active', 'verified_at' => now()->toDateTimeString()]))
            ->assertRedirect(route('verification.notice'));
        $user = User::firstOrFail();
        $this->assertSame('sertifikasi', $user->role);
        $this->assertSame('pending', $user->certificationBody->status);
        $this->assertNull($user->certificationBody->verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_registration_rejects_admin_role_duplicate_email_short_password_and_missing_institution_name(): void
    {
        $this->post('/register', $this->payload(['role' => 'admin']))->assertSessionHasErrors('role');
        $this->post('/register', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password_confirmation' => 'DifferentPassword123!']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['role' => 'sertifikasi']))->assertSessionHasErrors('body_name');
        $this->assertDatabaseCount('users', 0);
        User::factory()->create(['email' => 'Pelamar@Example.Test']);
        $this->post('/register', $this->payload())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public function test_validation_preserves_safe_fields_but_never_password_values(): void
    {
        $this->from('/register')->post('/register', $this->payload(['password_confirmation' => 'different']))->assertRedirect('/register');
        $this->get('/register')->assertSee('value="pelamar@example.test"', false)->assertDontSee('value="PasswordTesting123!"', false);
    }

    public function test_valid_verification_link_verifies_email_without_login_or_institution_approval(): void
    {
        Event::fake([Verified::class]);
        $body = CertificationBody::factory()->create(['user_id' => User::factory()->institution()->unverified()]);
        $user = $body->user;
        $url = $this->link($user);
        $this->get($url)->assertRedirect(route('verification.complete'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame('pending', $body->fresh()->status);
        $this->assertGuest();
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->get($url)->assertRedirect(route('verification.complete'));
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->get('/email/verified')->assertOk()->assertSee('Email berhasil diverifikasi');
    }

    public function test_expired_unsigned_and_wrong_hash_links_do_not_verify_accounts(): void
    {
        $user = User::factory()->unverified()->create();
        $this->get($this->link($user, -1))->assertForbidden()->assertSee('Tautan tidak berlaku');
        $this->get('/verify-email/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->get($this->link($user, 60, sha1('wrong@example.test')))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_for_a_previous_email_cannot_verify_an_updated_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->link($user);
        $user->update(['email' => 'changed@example.test']);
        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_uses_generic_responses_for_unknown_verified_and_unverified_emails(): void
    {
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();
        $messages = [];
        foreach ([$unverified->email, $verified->email, 'unknown@example.test'] as $email) {
            $response = $this->post('/verify-email/resend', ['email' => $email]);
            $response->assertRedirect(route('verification.notice'));
            $messages[] = session('status');
        }
        $this->assertCount(1, array_unique($messages));
        Notification::assertSentToTimes($unverified, VerifyAccountEmail::class, 1);
        Notification::assertNotSentTo($verified, VerifyAccountEmail::class);
    }

    public function test_resend_is_limited_per_email_and_per_ip(): void
    {
        $user = User::factory()->unverified()->create();
        $this->post('/verify-email/resend', ['email' => $user->email])->assertRedirect();
        $this->post('/verify-email/resend', ['email' => $user->email])->assertRedirect();
        Notification::assertSentToTimes($user, VerifyAccountEmail::class, 1);
        $this->post('/verify-email/resend', ['email' => $user->email])->assertRedirect();
        $this->post('/verify-email/resend', ['email' => $user->email])->assertStatus(429);
    }

    public function test_another_email_can_be_sent_after_the_cooldown(): void
    {
        $user = User::factory()->unverified()->create();
        RateLimiter::hit(EmailVerificationController::rateLimitKey($user->email), 60);
        $this->post('/verify-email/resend', ['email' => $user->email])->assertRedirect();
        Notification::assertNothingSent();
        $this->travel(61)->seconds();
        $this->post('/verify-email/resend', ['email' => $user->email])->assertRedirect();
        Notification::assertSentToTimes($user, VerifyAccountEmail::class, 1);
    }

    public function test_registration_post_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post('/register', [])->assertSessionHasErrors();
        }
        $this->post('/register', [])->assertStatus(429);
    }

    public function test_verification_email_template_escapes_names_and_contains_expiring_signed_link(): void
    {
        $user = User::factory()->unverified()->create(['name' => '<script>alert(1)</script>']);
        $message = (new VerifyAccountEmail)->toMail($user);
        $html = view($message->view, $message->viewData)->render();
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringContainsString('60 menit', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_registration_survives_mail_transport_failure_without_exposing_provider_errors(): void
    {
        Log::spy();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('Private SMTP diagnostic'));
        $this->post('/register', $this->payload())->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse(User::firstOrFail()->hasVerifiedEmail());
        $this->get('/verification-notice')->assertOk()->assertSee('email belum berhasil dikirim')->assertDontSee('Private SMTP diagnostic');
        Log::shouldHaveReceived('warning')->once()->with('Pengiriman email verifikasi belum berhasil.', ['user_id' => User::firstOrFail()->id]);
    }

    public function test_malformed_email_values_and_overlong_passwords_are_validation_errors(): void
    {
        $this->post('/register', $this->payload(['email' => ['not-an-email']]))->assertSessionHasErrors('email');
        $this->post('/register', $this->payload(['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_signed_verification_does_not_reactivate_a_disabled_account(): void
    {
        $user = User::factory()->inactive()->unverified()->create();
        $this->get($this->link($user))->assertRedirect(route('verification.complete'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($user->fresh()->is_active);
        $this->get('/email/verified')->assertOk();
        $this->get('/email/verified')->assertOk();
        $this->assertGuest();
    }

    public function test_signed_in_users_cannot_register_additional_accounts(): void
    {
        $this->actingAs(User::factory()->create())->post('/register', $this->payload())->assertRedirect(route('workspace', ['role' => 'pelamar', 'page' => 'dashboard']));
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public function test_complete_page_cannot_claim_verification_without_a_verified_account(): void
    {
        $this->get('/email/verified')->assertRedirect(route('verification.notice'));
    }
}
