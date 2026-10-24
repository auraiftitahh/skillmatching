<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verifikasi Email — Skill Matching</title></head>
<body style="margin:0;padding:24px 12px;background:#f3f8f5;font-family:Arial,sans-serif;color:#25352f;">
<table role="presentation" style="width:100%;max-width:600px;margin:0 auto;border-collapse:collapse;"><tr><td style="padding:20px 8px;color:#136d50;font-size:24px;font-weight:bold;">✧ skillmatching</td></tr>
<tr><td style="background:#fff;border:1px solid #dfe8e2;border-radius:16px;padding:32px 24px;">
<p style="margin:0 0 12px;color:#136d50;font-size:12px;font-weight:bold;letter-spacing:1px;">LANGKAH AWAL KARIER ANDA</p>
<h1 style="font-size:27px;line-height:1.3;margin:0 0 24px;">Konfirmasi email Anda</h1>
<p style="font-size:15px;line-height:1.7;">Halo {{ $name }},</p><p style="font-size:15px;line-height:1.7;color:#52675b;">Terima kasih telah mendaftar di Skill Matching. Konfirmasikan alamat email ini melalui tombol berikut.</p>
<table role="presentation" style="margin:28px 0;border-collapse:collapse;"><tr><td style="background:#136d50;border-radius:9px;text-align:center;"><a href="{{ $verificationUrl }}" style="display:inline-block;padding:15px 24px;color:#fff;text-decoration:none;font-size:15px;font-weight:bold;">Verifikasi email saya</a></td></tr></table>
<p style="font-size:13px;line-height:1.7;color:#60736a;">Tautan berlaku selama {{ $expiresInMinutes }} menit. Jika Anda tidak membuat akun ini, abaikan email ini.</p>
<p style="border-top:1px solid #dfe8e2;padding-top:20px;font-size:12px;line-height:1.7;color:#60736a;">Jika tombol tidak dapat dibuka, salin tautan ini ke browser:<br><a href="{{ $verificationUrl }}" style="color:#136d50;word-break:break-all;">{{ $verificationUrl }}</a></p>
</td></tr><tr><td style="padding:24px 12px;text-align:center;font-size:12px;color:#60736a;line-height:1.7;">Skill Matching<br>Keterampilan hari ini, peluang esok hari.</td></tr></table>
</body></html>
