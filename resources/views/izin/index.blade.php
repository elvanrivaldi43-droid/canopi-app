{{-- FILE: resources/views/izin/index.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Izin Saya</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:480px; margin:0 auto; padding-bottom:40px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:12px; }
  .btn-ajukan { background:#fbbf24; color:#0f172a; border:none; border-radius:10px; padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; display:block; text-align:center; margin-bottom:16px; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('absensi.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Izin Saya</div>
    <div style="color:#64748b; font-size:12px;">Riwayat pengajuan izin</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-box alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('izin.create') }}" class="btn-ajukan">
    📤 Ajukan Izin Baru
  </a>

  @forelse($izinList as $izin)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div style="font-size:15px; font-weight:700; color:#f1f5f9;">
          {{ $izin->tipeLabel() }}
        </div>
        <div style="font-size:12px; color:#64748b; margin-top:2px;">
          {{ $izin->tanggal->translatedFormat('l, d F Y') }}
        </div>
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">
          {{ $izin->alasan }}
        </div>
        @if($izin->catatan_mandor)
        <div style="font-size:11px; color:#fbbf24; margin-top:4px;">
          💬 {{ $izin->catatan_mandor }}
        </div>
        @endif
      </div>
      <span style="font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; white-space:nowrap;
        background:{{ $izin->warnaStatus() }}20; color:{{ $izin->warnaStatus() }}; border:1px solid {{ $izin->warnaStatus() }}40;">
        {{ $izin->statusLabel() }}
      </span>
    </div>
  </div>
  @empty
  <div class="card-dark text-center" style="padding:40px;">
    <div style="font-size:32px; margin-bottom:12px;">📋</div>
    <div style="color:#64748b;">Belum ada riwayat izin</div>
  </div>
  @endforelse

</div>
</body>
</html>