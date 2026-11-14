<?php

namespace Tests\Feature;

use App\Mail\AccountActionMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountEmailTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(): User
    {
        return User::create([
            'name' => 'Raka Pratama',
            'email' => 'raka-email@example.test',
            'password' => 'original-password-123',
            'role' => 'pelamar',
        ]);
    }

    public function test_registration_sends_branded_verification_with_valid_signed_link(): void
    {
        Mail::fake();
        $this->post('/register', [
            'name' => 'Raka Pratama', 'email' => 'new-email@example.test',
            'password' => 'new-account-password-123',
            'password_confirmation' => 'new-account-password-123',
        ])->assertRedirect('/verification-notice');

        $user = User::where('email', 'new-email@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $verificationUrl = null;
        Mail::assertSent(AccountActionMail::class, function ($mail) use (&$verificationUrl) {
            $verificationUrl = $mail->actionUrl;
            $mail->assertSeeInHtml('Verifikasi Email Saya');
            $mail->assertSeeInHtml('Halo, Raka Pratama.');
            $mail->assertSeeInText('Tautan berlaku 60 menit.');
            return $mail->hasTo('new-email@example.test') && $mail->purpose === 'verification';
        });
        Mail::assertSentCount(1);
        parse_str(parse_url($verificationUrl, PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(now()->addMinutes(60)->timestamp, (int) $query['expires'], 2);
        $this->assertArrayHasKey('signature', $query);
        $this->get($verificationUrl)->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_uses_same_email_and_expired_verification_stays_invalid(): void
    {
        Mail::fake();
        $user = $this->applicant();
        $this->post('/verify-email/resend', ['email' => $user->email])->assertSessionHas('success');
        $url = null;
        Mail::assertSent(AccountActionMail::class, function ($mail) use ($user, &$url) {
            $url = $mail->actionUrl;
            return $mail->hasTo($user->email) && $mail->purpose === 'verification';
        });
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_preserves_broker_token_and_applies_new_password(): void
    {
        Notification::fake();
        $user = $this->applicant();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, &$token) {
            $token = $notification->token;
            $mail = $notification->toMail($user);
            $this->assertInstanceOf(AccountActionMail::class, $mail);
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertSame('reset', $mail->purpose);
            $this->assertSame(route('password.reset', ['token' => $token, 'email' => $user->email]), $mail->actionUrl);
            $mail->assertSeeInHtml('Atur Ulang Kata Sandi');
            $mail->assertSeeInText('Kata sandi Anda tidak akan berubah.');
            return true;
        });
        $this->assertTrue(Password::tokenExists($user, $token));
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'replacement-password-123',
            'password_confirmation' => 'replacement-password-123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('replacement-password-123', $user->fresh()->password));
        $this->assertFalse(Password::tokenExists($user->fresh(), $token));
    }

    public function test_reset_mail_channel_produces_html_and_plain_text_without_smtp(): void
    {
        $user = $this->applicant();
        $user->sendPasswordResetNotification('preview-token');
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last();
        $message = $sent->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertSame('Atur Ulang Kata Sandi Anda — Skill Matching', $message->getSubject());
        $this->assertStringContainsString('background-color:#124e3e', $message->getHtmlBody());
        $this->assertStringContainsString('Halo, Raka Pratama.', $message->getHtmlBody());
        $this->assertStringContainsString('/reset-password/preview-token', $message->getTextBody());
        $this->assertStringNotContainsString('<table', $message->getTextBody());
    }

    public function test_html_escapes_recipient_name_and_includes_fallback_url(): void
    {
        $mail = new AccountActionMail('<script>alert(1)</script>', 'https://example.test/verify-email?signature=sample&expires=123', 'verification');
        $html = $mail->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('signature=sample&amp;expires=123', $html);
        $this->assertStringContainsString('Tombol tidak berfungsi?', $html);
        $mail->assertSeeInText('https://example.test/verify-email?signature=sample&expires=123');
    }
}
