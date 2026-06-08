{{-- FILE: resources/views/kasbon/saya.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Kasbon Saya</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing:border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; margin:0; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:480px; margin:0 auto; padding-bottom:100px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:14px; }
  .section-label { color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .alert-warning { background:rgba(245,158,11,0.2); border:1px solid #f59e0b; color:#fcd34d; }
  .form-control-dark { background:#0f172a; border:1px solid #475569; color:#f1f5f9; border-radius:8px; padding:10px 12px; width:100%; font-size:13px; }
  .form-control-dark:focus { border-color:#fbbf24; outline:none; }
  .btn-submit { width:100%; padding:14px; background:#fbbf24; color:#0f172a; border:none; border-radius:10px; font-weight:700; font-size:15px; cursor:pointer; }
  .btn-submit:disabled { background:#334155; color:#64748b; cursor:not-allowed; }
  .progress-bar-wrap { background:#334155; border-radius:4px; height:8px; margin-top:8px; overflow:hidden; }
  .progress-bar-fill { background:#10b981; height:100%; border-radius:4px; transition:width 0.3s; }
  .info-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #0f172a; font-size:13px; }
  .info-row:last-child { border-bottom:none; }
  .tab-wrap { display:flex; gap:8px; margin-bottom:16px; }
  .tab-btn { flex:1; padding:10px; border-radius:8px; border:1px solid #334155; background:#1e293b; color:#94a3b8; font-size:13px; font-weight:600; text-align:center; cursor:pointer; }
  .tab-btn.active { background:#fbbf24; color:#0f172a; border-color:#fbbf24; }
  .tab-content { display:none; }
  .tab-content.active { display:block; }
  .syarat-item { display:flex; gap:10px; align-items:flex-start; padding:8px 0; font-size:13px; border-bottom:1px solid #0f172a; }
  .syarat-item:last-child { border-bottom:none; }

  /* TTD Canvas */
  .ttd-wrap { border:2px dashed #475569; border-radius:8px; overflow:hidden; cursor:crosshair; }
  #ttdCanvas { width:100%; height:160px; background:#0f172a; display:block; touch-action:none; }
  .ttd-actions { display:flex; gap:8px; margin-top:8px; }
  .btn-clear { flex:1; padding:8px; background:#334155; color:#e2e8f0; border:none; border-radius:8px; font-size:13px; cursor:pointer; }

  /* Badge status */
  .badge-s { font-size:10px; padding:3px 10px; border-radius:20px; }
  .badge-pending  { background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); }
  .badge-aktif    { background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid rgba(239,68,68,0.3); }
  .badge-lunas    { background:rgba(16,185,129,0.2); color:#10b981; border:1px solid rgba(16,185,129,0.3); }
  .badge-ditolak  { background:rgba(100,116,139,0.2); color:#94a3b8; border:1px solid rgba(100,116,139,0.3); }
  .badge-ditunda  { background:rgba(99,102,241,0.2); color:#818cf8; border:1px solid rgba(99,102,241,0.3); }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ url('/dashboard') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Kasbon Saya</div>
    <div style="color:#64748b; font-size:12px;">{{ $user->name }}</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-box alert-success">✅ {{ session('success') }}</div>
  @endif
  @if(session('error'))
  <div class="alert-box alert-error">⚠️ {{ session('error') }}</div>
  @endif

  {{-- Tab --}}
  <div class="tab-wrap">
    <button class="tab-btn active" onclick="bukuTab('pengajuan',this)">➕ Ajukan</button>
    <button class="tab-btn" onclick="bukuTab('histori',this)">📜 Histori</button>
  </div>

  {{-- TAB PENGAJUAN --}}
  <div id="tab-pengajuan" class="tab-content active">

    {{-- Kasbon Aktif --}}
    @foreach($kasbonAktif as $kb)
    <div class="card-dark" style="border-color:rgba(239,68,68,0.4);margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <div>
          <div style="font-size:13px;font-weight:700;color:#f1f5f9;">{{ $kb->kategoriLabel() }}</div>
          <div style="font-size:11px;color:#64748b;">{{ $kb->keterangan }}</div>
        </div>
        <span class="badge-s badge-{{ $kb->status }}">{{ $kb->statusLabel() }}</span>
      </div>
      <div style="font-size:22px;font-weight:800;color:#ef4444;">Rp {{ number_format($kb->sisa_kasbon,0,',','.') }}</div>
      <div style="font-size:11px;color:#64748b;">Sisa dari Rp {{ number_format($kb->nominal,0,',','.') }}</div>
      @if($kb->status === 'aktif')
      <div class="progress-bar-wrap">
        @php $p = $kb->nominal > 0 ? 100 - (($kb->sisa_kasbon / $kb->nominal) * 100) : 0; @endphp
        <div class="progress-bar-fill" style="width:{{ $p }}%;"></div>
      </div>
      <div style="font-size:11px;color:#64748b;margin-top:4px;">Cicilan ke {{ $kb->cicilan_ke }}/{{ $kb->jumlah_cicilan }} · Rp {{ number_format($kb->cicilan_per_bulan,0,',','.') }}/bulan</div>
      @elseif($kb->status === 'pending')
      <div style="font-size:11px;color:#f59e0b;margin-top:6px;">⏳ Menunggu persetujuan owner</div>
      @elseif($kb->status === 'ditolak')
      <div style="font-size:11px;color:#ef4444;margin-top:6px;">❌ Ditolak: {{ $kb->alasan_tolak }}</div>
      @endif
    </div>
    @endforeach

    {{-- Syarat --}}
    <div class="card-dark">
      <div class="section-label">📋 Syarat Pengajuan</div>
      <div class="syarat-item">
        <span>{{ $syarat['masa_kerja'] ? '✅' : '❌' }}</span>
        <div>
          <div style="color:{{ $syarat['masa_kerja'] ? '#10b981' : '#ef4444' }};">Masa kerja minimal 1 tahun</div>
          <div style="font-size:11px;color:#64748b;">{{ $syarat['masa_kerja_bulan'] }} bulan bekerja</div>
        </div>
      </div>
      <div class="syarat-item">
        <span>{{ $syarat['slot_tersedia'] ? '✅' : '❌' }}</span>
        <div>
          <div style="color:{{ $syarat['slot_tersedia'] ? '#10b981' : '#ef4444' }};">Maksimal 2 kasbon aktif</div>
          <div style="font-size:11px;color:#64748b;">{{ $syarat['kasbon_aktif_count'] }}/2 slot terpakai</div>
        </div>
      </div>
      <div class="syarat-item">
        <span>{{ $syarat['bukan_sp3'] ? '✅' : '❌' }}</span>
        <div style="color:{{ $syarat['bukan_sp3'] ? '#10b981' : '#ef4444' }};">Status karyawan bukan SP3</div>
      </div>
      <div class="syarat-item">
        <span>{{ $syarat['gaji_aman'] ? '✅' : '❌' }}</span>
        <div style="color:{{ $syarat['gaji_aman'] ? '#10b981' : '#ef4444' }};">Gaji bersih tetap di atas Rp 500.000</div>
      </div>
    </div>

    {{-- Form Ajukan --}}
    @if($bisaAjukan)
    <div class="card-dark">
      <div class="section-label">➕ Form Pengajuan Kasbon</div>
      <form method="POST" action="{{ route('kasbon.karyawan.store') }}" id="formKasbon">
        @csrf

        {{-- Kategori --}}
        <div style="margin-bottom:12px;">
          <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Kategori Tujuan</label>
          <select name="kategori" class="form-control-dark" onchange="toggleLainnya(this.value)">
            <option value="kebutuhan_pribadi">🏠 Kebutuhan Pribadi</option>
            <option value="kesehatan">🏥 Kesehatan</option>
            <option value="pendidikan">📚 Pendidikan</option>
            <option value="renovasi_rumah">🔨 Renovasi Rumah</option>
            <option value="lainnya">📝 Lainnya</option>
          </select>
        </div>

        <div id="kategoriLainnya" style="display:none;margin-bottom:12px;">
          <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Sebutkan</label>
          <input type="text" name="kategori_lainnya" class="form-control-dark" placeholder="Jelaskan tujuan kasbon...">
        </div>

        {{-- Nominal --}}
        <div style="margin-bottom:12px;">
          <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Nominal Kasbon</label>
          <input type="number" name="nominal" class="form-control-dark" placeholder="Rp"
                 min="50000" max="{{ $maxKasbon }}" required oninput="hitungCicilan()">
          <div style="font-size:11px;color:#64748b;margin-top:4px;">
            Min: Rp 50.000 · Maks: Rp {{ number_format($maxKasbon,0,',','.') }} (3x gaji bulanan)
          </div>
        </div>

        {{-- Cicilan --}}
        <div style="margin-bottom:12px;">
          <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Jumlah Cicilan</label>
          <select name="jumlah_cicilan" class="form-control-dark" onchange="hitungCicilan()">
            @for($i=1;$i<=12;$i++)
            <option value="{{ $i }}">{{ $i }} bulan</option>
            @endfor
          </select>
        </div>

        {{-- Preview cicilan --}}
        <div id="previewCicilan" style="display:none;background:#0f172a;border-radius:8px;padding:12px;margin-bottom:12px;">
          <div style="font-size:11px;color:#64748b;">Cicilan per bulan:</div>
          <div style="font-size:22px;font-weight:700;color:#fbbf24;" id="nominalCicilan">Rp 0</div>
          <div style="font-size:11px;margin-top:4px;" id="statusGajiBersih"></div>
        </div>

        {{-- Keterangan --}}
        <div style="margin-bottom:16px;">
          <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Keterangan Detail</label>
          <textarea name="keterangan" class="form-control-dark" rows="3"
                    placeholder="Jelaskan keperluan kasbon secara detail..." required style="resize:none;"></textarea>
        </div>

        {{-- Surat Pernyataan --}}
        <div class="card-dark" style="background:#0f172a;margin-bottom:16px;">
          <div style="font-size:12px;font-weight:700;color:#fbbf24;margin-bottom:10px;">📄 Surat Pernyataan</div>
          <div style="font-size:12px;color:#94a3b8;line-height:1.7;margin-bottom:12px;">
            Saya yang bertanda tangan di bawah ini menyatakan bahwa:<br>
            1. Saya mengajukan kasbon sesuai kebutuhan yang saya sebutkan<br>
            2. Saya bersedia dipotong cicilan setiap bulan dari gaji<br>
            3. Jika saya resign, sisa kasbon akan dipotong dari gaji terakhir<br>
            4. Saya bertanggung jawab atas kasbon ini sepenuhnya
          </div>
          <div style="font-size:11px;color:#64748b;margin-bottom:8px;">Tanda tangan di bawah ini:</div>
          <div class="ttd-wrap">
            <canvas id="ttdCanvas"></canvas>
          </div>
          <div class="ttd-actions">
            <button type="button" class="btn-clear" onclick="bersihkanTTD()">🗑️ Ulangi TTD</button>
            <div style="flex:1;font-size:11px;color:#64748b;padding:8px;text-align:center;" id="ttdStatus">Tanda tangan belum ada</div>
          </div>
          <input type="hidden" name="ttd_digital" id="ttdData">
        </div>

        <button type="submit" class="btn-submit" id="btnAjukan" disabled>📤 Kirim Pengajuan Kasbon</button>
      </form>
    </div>
    @else
    <div class="alert-box alert-warning">
      ⚠️ Kamu belum memenuhi syarat untuk mengajukan kasbon saat ini.
    </div>
    @endif

  </div>{{-- end tab pengajuan --}}

  {{-- TAB HISTORI --}}
  <div id="tab-histori" class="tab-content">
    @forelse($semuaKasbon as $kb)
    <div class="card-dark" style="margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
        <div>
          <div style="font-size:13px;font-weight:700;color:#f1f5f9;">{{ $kb->kategoriLabel() }}</div>
          <div style="font-size:11px;color:#64748b;">{{ $kb->tanggal->format('d/m/Y') }} · {{ $kb->jumlah_cicilan }} bulan</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $kb->keterangan }}</div>
        </div>
        <span class="badge-s badge-{{ $kb->status }}">{{ $kb->statusLabel() }}</span>
      </div>

      <div style="font-size:18px;font-weight:700;color:#f1f5f9;">Rp {{ number_format($kb->nominal,0,',','.') }}</div>

      @if(in_array($kb->status, ['aktif','ditunda']))
      <div class="progress-bar-wrap">
        @php $p = $kb->nominal > 0 ? 100 - (($kb->sisa_kasbon / $kb->nominal) * 100) : 0; @endphp
        <div class="progress-bar-fill" style="width:{{ $p }}%;"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-top:4px;">
        <span>Terlunasi {{ round($p) }}%</span>
        <span>Sisa: Rp {{ number_format($kb->sisa_kasbon,0,',','.') }}</span>
      </div>
      <div style="font-size:11px;color:#fbbf24;margin-top:4px;">
        Cicilan ke {{ $kb->cicilan_ke }}/{{ $kb->jumlah_cicilan }} · Rp {{ number_format($kb->cicilan_per_bulan,0,',','.') }}/bulan
      </div>
      @elseif($kb->status === 'ditolak')
      <div style="font-size:11px;color:#ef4444;margin-top:6px;">❌ {{ $kb->alasan_tolak }}</div>
      @elseif($kb->status === 'lunas')
      <div style="font-size:11px;color:#10b981;margin-top:6px;">✅ Lunas</div>
      @endif

      {{-- Download surat pernyataan --}}
      @if($kb->ttd_digital)
      <button onclick="downloadSurat({{ $kb->id }})"
        style="width:100%;margin-top:10px;background:#334155;color:#e2e8f0;border:none;border-radius:8px;padding:8px;font-size:12px;cursor:pointer;">
        📄 Download Surat Pernyataan
      </button>
      @endif
    </div>
    @empty
    <div class="card-dark" style="text-align:center;padding:40px;">
      <div style="font-size:32px;margin-bottom:12px;">💳</div>
      <div style="color:#64748b;">Belum ada riwayat kasbon</div>
    </div>
    @endforelse
  </div>{{-- end tab histori --}}

</div>

<script>
// Tab
function bukuTab(tab, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  btn.classList.add('active');
}

// Kategori lainnya
function toggleLainnya(val) {
  document.getElementById('kategoriLainnya').style.display = val === 'lainnya' ? 'block' : 'none';
}

// Hitung cicilan
const gajiBulanan = {{ ($user->gaji_harian ?? 0) * 26 }};
const batasAman   = 500000;
const totalCicilanAktif = {{ $totalCicilanAktif }};

function hitungCicilan() {
  const nominal  = parseInt(document.querySelector('[name=nominal]').value) || 0;
  const cicilan  = parseInt(document.querySelector('[name=jumlah_cicilan]').value);
  const perBulan = Math.ceil(nominal / cicilan);
  const gajiBersih = gajiBulanan - totalCicilanAktif - perBulan;

  document.getElementById('previewCicilan').style.display = nominal > 0 ? 'block' : 'none';
  document.getElementById('nominalCicilan').textContent = 'Rp ' + perBulan.toLocaleString('id-ID');

  const statusEl = document.getElementById('statusGajiBersih');
  if (gajiBersih < batasAman) {
    statusEl.textContent = '⚠️ Gaji bersih Rp ' + gajiBersih.toLocaleString('id-ID') + ' (di bawah batas aman!)';
    statusEl.style.color = '#ef4444';
    document.getElementById('btnAjukan').disabled = true;
  } else {
    statusEl.textContent = '✅ Gaji bersih Rp ' + gajiBersih.toLocaleString('id-ID');
    statusEl.style.color = '#10b981';
    cekFormLengkap();
  }
}

// TTD Canvas
const canvas = document.getElementById('ttdCanvas');
const ctx    = canvas.getContext('2d');
let menggambar = false;
let adaTTD = false;

function resizeCanvas() {
  canvas.width  = canvas.offsetWidth;
  canvas.height = 160;
  ctx.strokeStyle = '#fbbf24';
  ctx.lineWidth   = 2;
  ctx.lineCap     = 'round';
}
resizeCanvas();

function getPosisi(e) {
  const rect = canvas.getBoundingClientRect();
  const clientX = e.touches ? e.touches[0].clientX : e.clientX;
  const clientY = e.touches ? e.touches[0].clientY : e.clientY;
  return { x: clientX - rect.left, y: clientY - rect.top };
}

canvas.addEventListener('mousedown',  e => { menggambar = true; ctx.beginPath(); const p = getPosisi(e); ctx.moveTo(p.x, p.y); });
canvas.addEventListener('mousemove',  e => { if (!menggambar) return; const p = getPosisi(e); ctx.lineTo(p.x, p.y); ctx.stroke(); adaTTD = true; simpanTTD(); });
canvas.addEventListener('mouseup',    () => { menggambar = false; });
canvas.addEventListener('touchstart', e => { e.preventDefault(); menggambar = true; ctx.beginPath(); const p = getPosisi(e); ctx.moveTo(p.x, p.y); });
canvas.addEventListener('touchmove',  e => { e.preventDefault(); if (!menggambar) return; const p = getPosisi(e); ctx.lineTo(p.x, p.y); ctx.stroke(); adaTTD = true; simpanTTD(); });
canvas.addEventListener('touchend',   () => { menggambar = false; });

function simpanTTD() {
  document.getElementById('ttdData').value = canvas.toDataURL();
  document.getElementById('ttdStatus').textContent = '✅ Tanda tangan tersimpan';
  document.getElementById('ttdStatus').style.color = '#10b981';
  cekFormLengkap();
}

function bersihkanTTD() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  adaTTD = false;
  document.getElementById('ttdData').value = '';
  document.getElementById('ttdStatus').textContent = 'Tanda tangan belum ada';
  document.getElementById('ttdStatus').style.color = '#64748b';
  document.getElementById('btnAjukan').disabled = true;
}

function cekFormLengkap() {
  const nominal = parseInt(document.querySelector('[name=nominal]').value) || 0;
  const ttd     = document.getElementById('ttdData').value;
  document.getElementById('btnAjukan').disabled = !(nominal > 0 && ttd && adaTTD);
}

function downloadSurat(id) {
  window.open('/kasbon-saya/' + id + '/surat', '_blank');
}
</script>
</body>
</html>