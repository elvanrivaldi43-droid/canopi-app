{{-- FILE: resources/views/izin/create.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Ajukan Izin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .content { padding:16px; padding-bottom:40px; max-width:480px; margin:0 auto; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:16px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .form-label { color:#94a3b8; font-size:13px; margin-bottom:6px; }
  .form-control, .form-select {
    background:#0f172a !important; border:1px solid #475569 !important;
    color:#f1f5f9 !important; border-radius:8px;
  }
  .form-control:focus, .form-select:focus { border-color:#fbbf24 !important; box-shadow:none !important; }
  .form-select option { background:#1e293b; }
  .tipe-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px; }
  .tipe-item { background:#0f172a; border:2px solid #334155; border-radius:10px; padding:14px; text-align:center; cursor:pointer; transition:all 0.2s; }
  .tipe-item:hover { border-color:#fbbf24; }
  .tipe-item.selected { border-color:#fbbf24; background:rgba(251,191,36,0.05); }
  .tipe-item input { display:none; }
  .tipe-icon { font-size:24px; display:block; margin-bottom:6px; }
  .tipe-label { font-size:13px; font-weight:600; color:#f1f5f9; }
  .tipe-info { font-size:10px; color:#64748b; margin-top:3px; }
  .btn-submit { width:100%; padding:14px; border-radius:10px; border:none; font-weight:700; font-size:16px; background:#fbbf24; color:#0f172a; }
  .btn-submit:disabled { background:#334155; color:#64748b; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .alert-warning { background:rgba(245,158,11,0.2); border:1px solid #f59e0b; color:#fcd34d; }
  .info-box { background:rgba(99,102,241,0.1); border:1px solid #6366f1; border-radius:10px; padding:12px; font-size:12px; color:#a5b4fc; margin-bottom:16px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('izin.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Ajukan Izin</div>
    <div style="color:#64748b; font-size:12px;">{{ now()->translatedFormat('l, d F Y') }}</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-box alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
  <div class="alert-box alert-error">{{ session('error') }}</div>
  @endif
  @if($errors->any())
  <div class="alert-box alert-error">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
  </div>
  @endif

  @if(!$bisaAjukan)
  <div class="alert-box alert-warning">
    ⏰ Pengajuan izin sudah tutup hari ini (batas jam 22:00). Coba lagi besok.
  </div>
  @endif

  <div class="info-box">
    ℹ️ <strong>Ketentuan pengajuan:</strong><br>
    • Izin & Cuti: ajukan H-1 sebelum jam 22:00<br>
    • Cuti: minimal H-3<br>
    • Sakit: bisa diajukan kapan saja, langsung disetujui<br>
    • Dinas Luar: diinput oleh mandor/owner
  </div>

  <form method="POST" action="{{ route('izin.store') }}" enctype="multipart/form-data">
    @csrf

    {{-- Pilih Tipe --}}
    <div class="card-dark">
      <div class="section-label">Jenis Izin</div>
      <div class="tipe-grid">
        <label class="tipe-item {{ old('tipe')=='sakit'?'selected':'' }}" onclick="pilihTipe('sakit',this)">
          <input type="radio" name="tipe" value="sakit" {{ old('tipe')=='sakit'?'checked':'' }}>
          <span class="tipe-icon">🏥</span>
          <div class="tipe-label">Sakit</div>
          <div class="tipe-info">Langsung disetujui</div>
        </label>
        <label class="tipe-item {{ old('tipe')=='izin'?'selected':'' }}" onclick="pilihTipe('izin',this)">
          <input type="radio" name="tipe" value="izin" {{ old('tipe')=='izin'?'checked':'' }}>
          <span class="tipe-icon">📋</span>
          <div class="tipe-label">Izin</div>
          <div class="tipe-info">Perlu persetujuan</div>
        </label>
        <label class="tipe-item {{ old('tipe')=='cuti'?'selected':'' }}" onclick="pilihTipe('cuti',this)">
          <input type="radio" name="tipe" value="cuti" {{ old('tipe')=='cuti'?'checked':'' }}>
          <span class="tipe-icon">🌴</span>
          <div class="tipe-label">Cuti</div>
          <div class="tipe-info">Min H-3, perlu approval</div>
        </label>
        <label class="tipe-item" style="opacity:0.4;cursor:not-allowed;">
          <span class="tipe-icon">🚗</span>
          <div class="tipe-label">Dinas Luar</div>
          <div class="tipe-info">Diinput mandor/owner</div>
        </label>
      </div>
    </div>

    {{-- Tanggal --}}
    <div class="card-dark">
      <div class="section-label">Tanggal Izin</div>
      <input type="date" name="tanggal" class="form-control"
             min="{{ $tanggalMin }}" max="{{ $tanggalMax }}"
             value="{{ old('tanggal', $tanggalMin) }}" required>
      <div style="color:#64748b; font-size:11px; margin-top:6px;">Minimal besok ({{ \Carbon\Carbon::parse($tanggalMin)->translatedFormat('d F Y') }})</div>
    </div>

    {{-- Alasan --}}
    <div class="card-dark">
      <div class="section-label">Alasan</div>
      <textarea name="alasan" class="form-control" rows="4"
                placeholder="Jelaskan alasan izin kamu..." required
                style="resize:none;">{{ old('alasan') }}</textarea>
    </div>

    {{-- Foto Surat (untuk sakit) --}}
    <div class="card-dark" id="fotoBox" style="{{ old('tipe')=='sakit'?'':'display:none;' }}">
      <div class="section-label">Foto Surat Sakit <span style="color:#64748b;">(opsional tapi dianjurkan)</span></div>
      <input type="file" name="foto_surat" class="form-control" accept="image/*">
      <div style="color:#64748b; font-size:11px; margin-top:6px;">Format JPG/PNG, maks 3MB</div>
    </div>

    <button type="submit" class="btn-submit" {{ !$bisaAjukan ? 'disabled' : '' }}>
      📤 Kirim Pengajuan Izin
    </button>
  </form>

</div>

<script>
function pilihTipe(tipe, el) {
  document.querySelectorAll('.tipe-item').forEach(t => t.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input').checked = true;
  document.getElementById('fotoBox').style.display = tipe === 'sakit' ? 'block' : 'none';
}
</script>
</body>
</html>