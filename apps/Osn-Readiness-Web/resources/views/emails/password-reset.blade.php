<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Atur Ulang Kata Sandi SIAP OSN</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Tautan aman untuk memperbarui kata sandi akun SIAP OSN Anda.
    </div>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f1f5f9;">
        <tr><td align="center" style="padding:40px 16px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px;border-radius:14px;overflow:hidden;background-color:#ffffff;">
                <tr><td style="background-color:#0759a5;padding:30px 36px;">
                    <div style="font-size:26px;font-weight:700;letter-spacing:.5px;color:#ffffff;">SIAP OSN</div>
                    <div style="margin-top:6px;font-size:13px;color:#d9ecff;">Platform Persiapan Olimpiade Sains Nasional</div>
                </td></tr>
                <tr><td style="padding:36px;">
                    <div style="font-size:12px;font-weight:700;letter-spacing:1px;color:#0759a5;text-transform:uppercase;">Keamanan Akun</div>
                    <h1 style="font-size:25px;line-height:1.3;margin:13px 0 18px;color:#172033;">Atur Ulang Kata Sandi</h1>
                    <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Halo,</p>
                    <p style="margin:0 0 24px;font-size:15px;line-height:1.7;">Kami menerima permintaan untuk mengatur ulang kata sandi akun SIAP OSN Anda. Klik tombol berikut untuk membuat kata sandi baru.</p>
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                        <td bgcolor="#0759a5" style="border-radius:8px;">
                            <a href="{{ $resetUrl }}" style="display:inline-block;padding:15px 24px;border:1px solid #0759a5;border-radius:8px;font-size:14px;font-weight:700;text-decoration:none;color:#ffffff;">Buat Kata Sandi Baru</a>
                        </td>
                    </tr></table>
                    <p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#475569;">Tautan ini berlaku selama <strong>{{ $expireMinutes }} menit</strong> dan hanya dapat digunakan satu kali.</p>
                    <p style="margin:12px 0 0;font-size:14px;line-height:1.7;color:#475569;">Jika Anda tidak meminta perubahan ini, abaikan email ini. Kata sandi akun Anda tidak akan berubah.</p>
                    <div style="height:1px;background-color:#e2e8f0;margin:28px 0 22px;"></div>
                    <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#64748b;">Jika tombol tidak berfungsi, salin tautan berikut ke browser:</p>
                    <p style="margin:0;font-size:12px;line-height:1.6;overflow-wrap:anywhere;word-break:break-word;"><a href="{{ $resetUrl }}" style="color:#0759a5;">{{ $resetUrl }}</a></p>
                    <p style="margin:26px 0 0;font-size:14px;line-height:1.7;">Salam,<br><strong>Tim SIAP OSN</strong></p>
                </td></tr>
            </table>
            <p style="max-width:560px;margin:18px 0 0;font-size:12px;line-height:1.6;color:#64748b;text-align:center;">Email otomatis terkait keamanan akun SIAP OSN. Mohon tidak membalas email ini.</p>
        </td></tr>
    </table>
</body>
</html>
