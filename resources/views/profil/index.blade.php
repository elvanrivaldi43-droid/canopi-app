{{-- FILE: resources/views/profil/index.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Profil Saya</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing:border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; margin:0; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:480px; margin:0 auto; padding-bottom:100px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:14px; }
  .section-label { color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .info-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #0f172a; font-size:13px; }
  .info-row:last-child { border-bottom:none; }
  .info-label { color:#64748b; }
  .info-value { color:#f1f5f9; font-weight:500; text-align:right; max-width:60%; }
  .locked-badge { font-size:10px; background:rgba(100,116,139,0.2); color:#64748b; border:1px solid #334155; padding:2px 8px; border-radius:10px; }
  .form-control-dark { background:#0f172a; border:1px solid #475569; color:#f1f5f9; border-radius:8px; padding:10px 12px; width:100%; font-size:13px; }
  .form-control-dark:focus { border-color:#fbbf24; outline:none; }
  .btn-save { width:100%; padding:14px; background:#fbbf24; color:#0f172a; border:none; border-radius:10px; font-weight:700; font-size:15px; cursor:pointer; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }

  /* Avatar */
  .avatar-wrap { text-align:center; padding:20px 0 10px; }
  .avatar { width:90px; height:90px; border-radius:50%; object-fit:cover; border:3px solid #fbbf24; }
  .avatar-placeholder { width:90px; height:90px; border-radius:50%; background:#334155; display:flex; align-items:center; justify-content:center; font-size:32px; border:3px solid #475569; margin:0 auto; }
  .level-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:12px; font-weight:600; margin-top:8px; }

  /* KPI mini */
  .kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
  .kpi-item { background:#0f172a; border-radius:8px; padding:10px; text-align:center; }
  .kpi-val { font-size:20px; font-weight:700; }
  .kpi-lbl { font-size:10px; color:#64748b; margin-top:2px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ url('/dashboard') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Profil Saya</div>
    <div style="color:#64748b; font-size:12px;">{{ now()->translatedFormat('d F Y') }}</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-box alert-success">✅ {{ session('success') }}</div>
  @endif
  @if(session('error'))
  <div class="alert-box alert-error">⚠️ {{ session('error') }}</div>
  @endif

  {{-- Avatar & Info Utama --}}
  <div class="card-dark" style="text-align:center;">
    <div class="avatar-wrap">
      @if($user->foto_profil)
        <img src="{{ Storage::url($user->foto_profil) }}" class="avatar" alt="foto">
      @else
        <div class="avatar-placeholder">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
      @endif
    </div>
    <div style="font-size:20px; font-weight:700; color:#f1f5f9; margin-top:8px;">{{ $user->name }}</div>
    <div style="color:#64748b; font-size:13px;">{{ $user->jabatan }}</div>
    @php
      $levelColors = [2=>'#06b6d4',3=>'#8b5cf6',4=>'#f59e0b',5=>'#10b981',6=>'#3b82f6',7=>'#ec4899'];
      $levelLabels = [1=>'Owner',2=>'Admin',3=>'Supervisor',4=>'Marketing',5=>'Teknisi',6=>'Driver',7=>'Toko'];
      $lc = $levelColors[$user->level] ?? '#64748b';
    @endphp
    <span class="level-badge" style="background:{{ $lc }}20;color:{{ $lc }};border:1px solid {{ $lc }}40;">
      {{ $levelLabels[$user->level] ?? '-' }}
    </span>
  </div>

  {{-- Statistik Bulan Ini --}}
  <div class="card-dark">
    <div class="section-label">📊 Statistik Bulan Ini</div>
    <div class="kpi-grid">
      <div class="kpi-item">
        <div class="kpi-val" style="color:#10b981;">{{ $stats['hadir'] }}</div>
        <div class="kpi-lbl">Hadir</div>
      </div>
      <div class="kpi-item">
        <div class="kpi-val" style="color:#ef4444;">{{ $stats['alpha'] }}</div>
        <div class="kpi-lbl">Alpha</div>
      </div>
      <div class="kpi-item">
        <div class="kpi-val" style="color:#f59e0b;">{{ $stats['telat'] }}</div>
        <div class="kpi-lbl">Telat</div>
      </div>
      <div class="kpi-item">
        <div class="kpi-val" style="color:#fbbf24; font-size:14px;">{{ $stats['kelas_kpi'] }}</div>
        <div class="kpi-lbl">KPI</div>
      </div>
      <div class="kpi-item" style="grid-column:span 2;">
        <div class="kpi-val" style="color:#06b6d4; font-size:14px;">Rp {{ number_format($stats['total_gaji'],0,',','.') }}</div>
        <div class="kpi-lbl">Estimasi Gaji</div>
      </div>
    </div>
  </div>

  {{-- Data Tidak Bisa Diedit --}}
  <div class="card-dark">
    <div class="section-label">🔒 Data Resmi <span class="locked-badge">Hanya Admin</span></div>
    <div class="info-row">
      <span class="info-label">Nama Lengkap</span>
      <span class="info-value">{{ $user->name }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Jabatan</span>
      <span class="info-value">{{ $user->jabatan }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Gaji Harian</span>
      <span class="info-value">Rp {{ number_format($user->gaji_harian ?? 0, 0, ',', '.') }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Uang Makan</span>
      <span class="info-value">Rp {{ number_format($user->uang_makan ?? 0, 0, ',', '.') }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Bank</span>
      <span class="info-value">{{ $user->nama_bank ?? '—' }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">No. Rekening</span>
      <span class="info-value">{{ $user->no_rekening ?? '—' }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Email</span>
      <span class="info-value" style="font-size:12px;">{{ $user->email }}</span>
    </div>
  </div>

  {{-- Form Edit Data Diri --}}
  <div class="card-dark">
    <div class="section-label">✏️ Edit Data Diri</div>
    <form method="POST" action="{{ route('profil.update') }}">
      @csrf
      @method('PUT')

      <div style="margin-bottom:12px;">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">📱 No. HP / WhatsApp</label>
        <input type="text" name="no_hp" class="form-control-dark"
               value="{{ old('no_hp', $user->no_hp) }}"
               placeholder="08xxxxxxxxxx">
      </div>

      <div style="margin-bottom:12px;">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">🏠 Alamat</label>
        <textarea name="alamat" class="form-control-dark" rows="3"
                  placeholder="Alamat lengkap kamu..." style="resize:none;">{{ old('alamat', $user->alamat) }}</textarea>
      </div>

      <div style="margin-bottom:6px;">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">🆘 Kontak Darurat</label>
      </div>
      <div style="margin-bottom:12px;">
        <input type="text" name="nama_kontak_darurat" class="form-control-dark"
               value="{{ old('nama_kontak_darurat', $user->nama_kontak_darurat) }}"
               placeholder="Nama kontak darurat" style="margin-bottom:8px;">
        <input type="text" name="no_kontak_darurat" class="form-control-dark"
               value="{{ old('no_kontak_darurat', $user->no_kontak_darurat) }}"
               placeholder="No HP kontak darurat">
      </div>

      <button type="submit" class="btn-save">💾 Simpan Perubahan</button>
    </form>
  </div>

</div>
</body>
</html>