{{-- FILE: resources/views/emails/undangan-karyawan.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Undangan Registrasi</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 20px;">
  <tr><td align="center">
    <table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
      <!-- Header -->
      <tr><td style="background:#0f172a;padding:32px;text-align:center;">
        <div style="background:#f59e0b;width:56px;height:56px;border-radius:12px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:28px;line-height:56px;">🏠</div>
        <h1 style="color:#f59e0b;margin:0;font-size:22px;letter-spacing:.5px;">Pusat Kanopi BSD</h1>
        <p style="color:#94a3b8;margin:6px 0 0;font-size:13px;">CanopiBSD Management System</p>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:32px;">
        <h2 style="color:#0f172a;font-size:18px;margin:0 0 12px;">Selamat datang! 👋</h2>
        <p style="color:#475569;line-height:1.6;margin:0 0 16px;">Kamu telah didaftarkan sebagai <strong>{{ $level }}</strong> ({{ $jabatan }}) di sistem manajemen Pusat Kanopi BSD.</p>
        <p style="color:#475569;line-height:1.6;margin:0 0 24px;">Klik tombol di bawah untuk melengkapi data diri dan membuat password akun kamu. <strong>Link ini berlaku selama 24 jam.</strong></p>
        <!-- CTA Button -->
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="center" style="padding:8px 0 24px;">
            <a href="{{ $link }}" style="background:#f59e0b;color:#0f172a;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:15px;display:inline-block;">
              ✅ Lengkapi Data & Buat Password
            </a>
          </td></tr>
        </table>
        <p style="color:#94a3b8;font-size:12px;margin:0 0 8px;">Atau copy link berikut ke browser:</p>
        <p style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 14px;font-size:12px;color:#475569;word-break:break-all;margin:0;">{{ $link }}</p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e2e8f0;">
        <p style="color:#94a3b8;font-size:12px;margin:0;text-align:center;">Jika kamu tidak merasa mendaftar, abaikan email ini. © {{ date('Y') }} Pusat Kanopi BSD</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
