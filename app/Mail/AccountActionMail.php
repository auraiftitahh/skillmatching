<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use InvalidArgumentException;

class AccountActionMail extends Mailable
{
    public function __construct(
        public string $recipientName,
        public string $actionUrl,
        public string $purpose,
        public int $expiresInMinutes = 60,
    ) {
        if (! in_array($purpose, ['verification', 'reset'], true)) {
            throw new InvalidArgumentException('Unsupported account email purpose.');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'verification'
            ? 'Verifikasi Email Anda — Skill Matching'
            : 'Atur Ulang Kata Sandi Anda — Skill Matching');
    }

    public function content(): Content
    {
        $verification = $this->purpose === 'verification';

        return new Content(
            view: 'emails.account-action',
            text: 'emails.account-action-text',
            with: [
                'websiteUrl' => rtrim((string) config('app.url'), '/'),
                'displayName' => trim($this->recipientName) !== '' ? $this->recipientName : 'Pengguna Skill Matching',
                'eyebrow' => $verification ? 'VERIFIKASI AKUN' : 'KEAMANAN AKUN',
                'heading' => $verification ? 'Satu langkah lagi untuk memulai.' : 'Kembali ke ruang karier Anda.',
                'subtitle' => $verification ? 'Konfirmasi email Anda dan mulai jelajahi peluang berikutnya.' : 'Buat kata sandi baru untuk mengakses akun Anda kembali.',
                'introduction' => $verification
                    ? 'Terima kasih telah bergabung dengan Skill Matching. Konfirmasi alamat email Anda melalui tombol berikut untuk melanjutkan ke akun Anda.'
                    : 'Kami menerima permintaan untuk mengatur ulang kata sandi akun Skill Matching Anda. Gunakan tombol berikut untuk membuat kata sandi baru.',
                'actionLabel' => $verification ? 'Verifikasi Email Saya' : 'Atur Ulang Kata Sandi',
                'safetyNote' => $verification
                    ? 'Jika Anda tidak membuat akun Skill Matching, Anda dapat mengabaikan email ini.'
                    : 'Jika Anda tidak meminta perubahan ini, abaikan email ini. Kata sandi Anda tidak akan berubah.',
                'expiryNote' => $verification
                    ? 'Jika tautan kedaluwarsa, minta kirim ulang verifikasi melalui website.'
                    : 'Jika tautan kedaluwarsa, ajukan permintaan baru melalui halaman Lupa Kata Sandi.',
            ],
        );
    }
}
