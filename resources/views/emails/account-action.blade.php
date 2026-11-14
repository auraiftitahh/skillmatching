<!doctype html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $heading }}</title>
    <!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
        table, td { mso-table-lspace:0; mso-table-rspace:0; }
        table { border-collapse:collapse; }
        @media screen and (max-width:620px) {
            .outer-pad { padding:20px 12px !important; }
            .content-pad { padding:28px 24px !important; }
            .hero-heading { font-size:28px !important; line-height:35px !important; }
            .action-table { width:100% !important; }
            .action-link { display:block !important; padding:17px 12px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;width:100%;background-color:#f3f7f4;font-family:'DM Sans',Arial,Helvetica,sans-serif;color:#25352f;">
    <div style="display:none;font-size:1px;line-height:1px;color:#f3f7f4;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $subtitle }} Tautan berlaku {{ $expiresInMinutes }} menit.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f3f7f4">
        <tr><td class="outer-pad" align="center" style="padding:40px 16px;">
            <!--[if mso]><table role="presentation" width="600" align="center"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                <tr><td style="background-color:#ffffff;border:1px solid #e0e9e3;border-radius:20px;overflow:hidden;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr><td class="content-pad" style="padding:28px 40px;background-color:#ffffff;">
                            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                                <td width="40" height="40" align="center" bgcolor="#136d50" style="width:40px;height:40px;border-radius:11px;font-family:Arial,sans-serif;font-size:28px;line-height:40px;color:#ffffff;">&#10022;</td>
                                <td style="padding-left:11px;"><a href="{{ $websiteUrl }}" style="color:#124e3e;text-decoration:none;font-size:25px;line-height:30px;font-weight:800;letter-spacing:-1px;">skillmatching</a></td>
                            </tr></table>
                        </td></tr>
                        <tr><td class="content-pad" bgcolor="#124e3e" style="padding:34px 40px;background-color:#124e3e;background-image:linear-gradient(120deg,#124e3e,#136d50);">
                            <p style="margin:0 0 15px;color:#c5e7d4;font-size:11px;font-weight:700;line-height:18px;letter-spacing:2px;">{{ $eyebrow }}</p>
                            <h1 class="hero-heading" style="margin:0 0 13px;color:#ffffff;font-size:34px;line-height:42px;font-weight:700;letter-spacing:-0.8px;">{{ $heading }}</h1>
                            <p style="margin:0;color:#dceee5;font-size:15px;line-height:25px;">{{ $subtitle }}</p>
                        </td></tr>
                        <tr><td class="content-pad" style="padding:34px 40px 32px;background-color:#ffffff;">
                            <p style="margin:0 0 12px;color:#25352f;font-size:18px;line-height:27px;font-weight:700;overflow-wrap:anywhere;">Halo, {{ $displayName }}.</p>
                            <p style="margin:0 0 25px;color:#5e6c63;font-size:15px;line-height:26px;">{{ $introduction }}</p>
                            <table class="action-table" role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 26px;"><tr><td align="center" bgcolor="#136d50" style="border-radius:10px;background-color:#136d50;mso-padding-alt:16px 28px;">
                                <a class="action-link" href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:16px 28px;border:1px solid #136d50;border-radius:10px;color:#ffffff;font-size:15px;line-height:22px;font-weight:700;text-align:center;text-decoration:none;mso-padding-alt:0;">{{ $actionLabel }} <span aria-hidden="true">&rarr;</span></a>
                            </td></tr></table>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td bgcolor="#e9f6ef" style="padding:16px 18px;border:1px solid #d5eadd;border-radius:10px;background-color:#e9f6ef;">
                                <p style="margin:0 0 5px;color:#124e3e;font-size:13px;line-height:21px;font-weight:700;">Tautan berlaku {{ $expiresInMinutes }} menit</p>
                                <p style="margin:0;color:#45715a;font-size:13px;line-height:21px;">{{ $expiryNote }}</p>
                            </td></tr></table>
                            <p style="margin:23px 0 0;color:#76817c;font-size:13px;line-height:22px;">{{ $safetyNote }}</p>
                        </td></tr>
                        <tr><td class="content-pad" style="padding:24px 40px 28px;border-top:1px solid #e6ebe8;background-color:#fafcfb;">
                            <p style="margin:0 0 9px;color:#5e6c63;font-size:12px;line-height:20px;">Tombol tidak berfungsi? Salin dan buka tautan berikut di browser Anda:</p>
                            <p style="margin:0;font-size:12px;line-height:20px;word-break:break-all;overflow-wrap:anywhere;"><a href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" style="color:#136d50;text-decoration:underline;word-break:break-all;">{{ $actionUrl }}</a></p>
                        </td></tr>
                    </table>
                </td></tr>
                <tr><td align="center" style="padding:25px 20px 0;">
                    <p style="margin:0 0 7px;color:#45715a;font-size:13px;line-height:21px;font-weight:700;">Keterampilan hari ini, peluang esok hari.</p>
                    <p style="margin:0;color:#76817c;font-size:11px;line-height:19px;">Email ini dikirim otomatis oleh Skill Matching.<br>Mohon tidak membalas email ini.</p>
                    <p style="margin:11px 0 0;color:#8c9b92;font-size:11px;line-height:18px;">&copy; {{ date('Y') }} Skill Matching</p>
                </td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
    </table>
</body>
</html>
