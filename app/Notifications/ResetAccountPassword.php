<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetAccountPassword extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atur ulang kata sandi Anda — Skill Matching')
            ->view('emails.reset-password', [
                'name' => $notifiable->name,
                'resetUrl' => $this->resetUrl($notifiable),
                'expiresInMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}
