<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyAccountEmail extends VerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi email Anda — Skill Matching')
            ->view('emails.verify-account', [
                'name' => $notifiable->name,
                'verificationUrl' => $this->verificationUrl($notifiable),
                'expiresInMinutes' => (int) config('auth.verification.expire', 60),
            ]);
    }
}
