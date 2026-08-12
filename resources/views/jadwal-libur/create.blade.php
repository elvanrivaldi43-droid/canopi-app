{{-- FILE: resources/views/jadwal-libur/create.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Ajukan Jadwal Libur</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .content { padding:16px; padding-bottom:40px; max-width:480px; margin:0 auto; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:16px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .form-control { background:#0f172a !important; border:1px solid #475569 !important; color:#f1f5f9 !important; border-radius:8px; }
  .form-control:focus { border-color:#fbbf24 !important; box-shadow:none !important; }
  .jenis-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px; }
  .jenis-item { background:#0f172a; border:2px solid #334155; border-radius:10px; padding:14px 8px; text-align:center; cursor:pointer; transition:all 0.2s; }
  .jenis-item:hover { border-color:#fbbf24; }
  .jenis-item.selected { border-color:#fbbf24; background:rgba(251,191,36,0.05); }
  .jenis-item input { display:none; }
  .jenis-icon { font-size:22px; display:block; margin-bottom:6px; }
  .jenis-label { font-size:13px; font-weight:600; color:#f1f5f9; }
  .jenis-info { font-size:10px; color:#64748b; margin-top:3px; }
  .btn-submit { width:100%; padding:14px; border-radius:10px; border:none; font-weight:700; font-size:16px; background:#fbbf24; color:#0f172a; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .info-box { background:rgba(99,102,241,0.1); border:1px solid #6366f1; border-radius:10px; padding:12px; font-size:12px; color:#a5b4fc; margin-bottom:16px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('jadwal-libur.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Ajukan Jadwal Libur</div>
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

  <div class="info-box">
    ℹ️ <strong>Tukar</strong>: geser 1 hari libur ke tanggal lain. <strong>Skip</strong>: batalkan 1 hari libur tanpa ganti. <strong>Tambah</strong>: libur ekstra di luar jadwal. Butuh persetujuan Owner/Mandor.
  </div>

  <form method="POST" action="{{ route('jadwal-libur.store') }}">
    @csrf

    {{-- Jenis --}}
    <div class="card-dark">
      <div class="section-label">Jenis Ajuan</div>
      <div class="jenis-grid">
        @if($punyaLiburDefault)
        <label class="jenis-item {{ old('jenis')=='tukar'?'selected':'' }}" onclick="pilihJenis('tukar',this)">
          <input type="radio" name="jenis" value="tukar" {{ old('jenis')=='tukar'?'checked':'' }}>
          <span class="jenis-icon">🔄</span>
          <div class="jenis-label">Tukar</div>
          <div class="jenis-info">Geser libur ke tanggal lain</div>
        </label>
        <label class="jenis-item {{ old('jenis')=='batal'?'selected':'' }}" onclick="pilihJenis('batal',this)">
          <input type="radio" name="jenis" value="batal" {{ old('jenis')=='batal'?'checked':'' }}>
          <span class="jenis-icon">🚫</span>
          <div class="jenis-label">Skip</div>
          <div class="jenis-info">Batalkan, tanpa ganti</div>
        </label>
        @endif
        <label class="jenis-item {{ old('jenis')=='tambah'?'selected':'' }}" onclick="pilihJenis('tambah',this)">
          <input type="radio" name="jenis" value="tambah" {{ old('jenis')=='tambah'?'checked':'' }}>
          <span class="jenis-icon">➕</span>
          <div class="jenis-label">Tambah</div>
          <div class="jenis-info">Libur ekstra</div>
        </label>
      </div>
      @if(!$punyaLiburDefault)
      <div style="color:#64748b; font-size:11px;">Kamu belum punya jadwal libur default, jadi cuma bisa ajukan Tambah.</div>
      @endif
    </div>

    {{-- Tukar: 2 tanggal --}}
    <div class="card-dark grup-tanggal" id="grup-tukar" style="display:none;">
      <div class="section-label">Tanggal Lama (dibatalkan)</div>
      <select name="tanggal" class="form-control" disabled>
        <option value="">-- Pilih tanggal libur default kamu --</option>
        @foreach($tanggalKandidat as $tgl)
        <option value="{{ $tgl }}" {{ old('tanggal')==$tgl?'selected':'' }}>{{ \Carbon\Carbon::parse($tgl)->translatedFormat('l, d F Y') }}</option>
        @endforeach
      </select>
      <div class="section-label" style="margin-top:16px;">Tanggal Baru (pengganti)</div>
      <input type="date" name="tanggal_baru" class="form-control" disabled
             min="{{ $jendelaAwal->format('Y-m-d') }}" max="{{ $jendelaAkhir->format('Y-m-d') }}"
             value="{{ old('tanggal_baru') }}">
      <div style="color:#64748b; font-size:11px; margin-top:6px;">Harus hari yang normalnya kamu kerja, dalam sisa minggu ini atau minggu depan.</div>
    </div>

    {{-- Skip: 1 tanggal dari kandidat --}}
    <div class="card-dark grup-tanggal" id="grup-batal" style="display:none;">
      <div class="section-label">Tanggal (dibatalkan)</div>
      <select name="tanggal" class="form-control" disabled>
        <option value="">-- Pilih tanggal libur default kamu --</option>
        @foreach($tanggalKandidat as $tgl)
        <option value="{{ $tgl }}" {{ old('tanggal')==$tgl?'selected':'' }}>{{ \Carbon\Carbon::parse($tgl)->translatedFormat('l, d F Y') }}</option>
        @endforeach
      </select>
    </div>

    {{-- Tambah: 1 tanggal bebas --}}
    <div class="card-dark grup-tanggal" id="grup-tambah" style="display:none;">
      <div class="section-label">Tanggal (libur ekstra)</div>
      <input type="date" name="tanggal" class="form-control" disabled
             min="{{ $tanggalMin }}" value="{{ old('tanggal', $tanggalMin) }}">
      <div style="color:#64748b; font-size:11px; margin-top:6px;">Minimal besok ({{ \Carbon\Carbon::parse($tanggalMin)->translatedFormat('d F Y') }}), bebas jauh ke depan.</div>
    </div>

    {{-- Alasan --}}
    <div class="card-dark">
      <div class="section-label">Alasan <span style="color:#64748b; text-transform:none; letter-spacing:0;">(opsional)</span></div>
      <textarea name="alasan" class="form-control" rows="3"
                placeholder="Mis. tukar sama Budi, ada acara keluarga, dst."
                style="resize:none;">{{ old('alasan') }}</textarea>
    </div>

    <button type="submit" class="btn-submit">📤 Kirim Ajuan</button>
  </form>

</div>

<script>
function pilihJenis(jenis, el) {
  document.querySelectorAll('.jenis-item').forEach(t => t.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input').checked = true;

  document.querySelectorAll('.grup-tanggal').forEach(g => {
    const aktif = g.id === 'grup-' + jenis;
    g.style.display = aktif ? 'block' : 'none';
    g.querySelectorAll('input, select').forEach(f => f.disabled = !aktif);
  });
}

@if(old('jenis'))
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.querySelector('.jenis-item.selected');
  if (sel) pilihJenis('{{ old('jenis') }}', sel);
});
@endif
</script>
</body>
</html>
